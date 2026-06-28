<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use App\Services\DistanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DispatcherController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DispatcherConnection::class);

        return Inertia::render('Freight/Dispatcher/Index', [
            'stats' => [
                'loadsWithoutBids' => FreightLoad::active()->where('bids_count', 0)->count(),
                'urgentLoads' => FreightLoad::active()->where('is_urgent', true)->count(),
                'newLoads24h' => FreightLoad::active()->where('created_at', '>=', now()->subDay())->count(),
                'onlineVehicles' => Vehicle::visibleOnMap()->where('is_online', true)->count(),
                'openConnections' => DispatcherConnection::whereIn('status', ['proposed', 'contacted'])->count(),
                'openComplaints' => Complaint::whereIn('status', ['new', 'in_review'])->count(),
            ],
            'loads' => FreightLoad::active()->with('company')->latest()->limit(15)->get(),
            'vehicles' => Vehicle::visibleOnMap()->with('company')->latest('last_location_at')->limit(15)->get(),
            'connections' => DispatcherConnection::with(['freightLoad', 'dispatcher', 'carrier'])->latest()->limit(15)->get(),
        ]);
    }

    public function connections(): Response
    {
        Gate::authorize('viewAny', DispatcherConnection::class);

        return Inertia::render('Freight/Dispatcher/Connections', [
            'connections' => DispatcherConnection::with(['freightLoad', 'dispatcher', 'shipper', 'carrier', 'vehicle'])
                ->latest()
                ->paginate(30),
        ]);
    }

    public function show(DispatcherConnection $connection): Response
    {
        Gate::authorize('view', $connection);

        return Inertia::render('Freight/Dispatcher/ConnectionShow', [
            'connection' => $connection->load(['freightLoad.company', 'shipper.company', 'carrier.company', 'vehicle', 'bid']),
            'auditLogs' => AuditLog::with('actor')
                ->where('entity_type', DispatcherConnection::class)
                ->where('entity_id', $connection->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'disclaimer' => config('freight.legal_disclaimer'),
        ]);
    }

    public function nearestCarriers(Request $request, FreightLoad $load, DistanceService $distance): Response
    {
        Gate::authorize('dispatch', $load);
        abort_if(! $load->loading_lat || ! $load->loading_lng, 422);

        $filters = $request->validate([
            'body_type' => ['nullable', 'string', 'max:255'],
            'online' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
        ]);

        $vehicles = Vehicle::visibleOnMap()
            ->with(['carrier.company', 'company', 'assignedDriver'])
            ->when($filters['body_type'] ?? null, fn ($query, $bodyType) => $query->where('body_type', $bodyType))
            ->when($request->boolean('online'), fn ($query) => $query->where('is_online', true))
            ->when($request->boolean('verified'), fn ($query) => $query->whereHas('company', fn ($company) => $company->where('verification_status', 'verified')))
            ->get()
            ->map(function (Vehicle $vehicle) use ($load, $distance) {
                $distanceKm = $distance->distanceKm(
                    (float) $load->loading_lat,
                    (float) $load->loading_lng,
                    (float) $vehicle->current_lat,
                    (float) $vehicle->current_lng,
                );

                return [
                    ...$this->dispatcherVehiclePayload($vehicle, $load),
                    'distance_km' => round($distanceKm, 1),
                    'match_score' => $this->candidateScore($vehicle, $load, $distanceKm),
                    'match_warnings' => $this->candidateWarnings($vehicle, $load),
                ];
            })
            ->sortBy([
                ['match_score', 'desc'],
                ['distance_km', 'asc'],
            ])
            ->values()
            ->take(25);

        return Inertia::render('Freight/Dispatcher/Candidates', [
            'load' => $this->dispatcherLoadPayload($load->load(['company', 'dispatcherConnections'])),
            'vehicles' => $vehicles,
            'filters' => [
                'body_type' => $filters['body_type'] ?? '',
                'online' => $request->boolean('online'),
                'verified' => $request->boolean('verified'),
            ],
            'filterOptions' => [
                'bodyTypes' => Vehicle::visibleOnMap()
                    ->whereNotNull('body_type')
                    ->distinct()
                    ->orderBy('body_type')
                    ->pluck('body_type')
                    ->values(),
            ],
            'disclaimer' => config('freight.legal_disclaimer'),
        ]);
    }

    public function storeConnection(Request $request, AuditLogService $audit): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'load_id' => ['required', 'exists:loads,id'],
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'carrier_id' => ['nullable', 'exists:users,id'],
            'contact_method' => ['nullable', 'in:phone,email,messenger,platform_notification,other'],
            'summary' => ['nullable', 'string', 'max:255'],
            'internal_comment' => ['nullable', 'string', 'max:2000'],
            'shipper_message' => ['nullable', 'string', 'max:2000'],
            'carrier_message' => ['nullable', 'string', 'max:2000'],
        ]);

        $load = FreightLoad::with('company')->findOrFail($data['load_id']);
        Gate::authorize('dispatch', $load);
        $vehicle = ! empty($data['vehicle_id']) ? Vehicle::with('company')->findOrFail($data['vehicle_id']) : null;
        $carrierId = $data['carrier_id'] ?? $vehicle?->carrier_id;

        $connection = DispatcherConnection::create([
            ...$data,
            'dispatcher_id' => $user->id,
            'shipper_id' => $load->shipper_id,
            'shipper_company_id' => $load->company_id,
            'carrier_id' => $carrierId,
            'carrier_company_id' => $vehicle?->company_id,
            'status' => 'proposed',
            'contact_method' => $data['contact_method'] ?? 'platform_notification',
        ]);

        FreightNotification::create([
            'user_id' => $load->shipper_id,
            'type' => 'dispatcher_connection',
            'title' => 'Диспетчер подобрал перевозчика',
            'message' => trim((($data['shipper_message'] ?? null) ?: 'По вашему грузу '.$load->title.' предложен перевозчик '.($vehicle?->company?->name ?? 'из базы платформы').'.').' '.config('freight.notification_disclaimer')),
            'data_json' => ['dispatcher_connection_id' => $connection->id],
        ]);

        if ($carrierId) {
            FreightNotification::create([
                'user_id' => $carrierId,
                'type' => 'dispatcher_connection',
                'title' => 'Диспетчер подобрал груз',
                'message' => trim((($data['carrier_message'] ?? null) ?: 'Для вашего транспорта предложен груз '.$load->title.'.').' '.config('freight.notification_disclaimer')),
                'data_json' => ['dispatcher_connection_id' => $connection->id],
            ]);
        }

        $audit->record('dispatcher_connection.created', $connection, null, ['status' => $connection->status]);

        return redirect()->route('dispatcher.connections.show', $connection)->with('status', 'Ручное соединение создано.');
    }

    public function updateConnection(Request $request, DispatcherConnection $connection, AuditLogService $audit): RedirectResponse
    {
        Gate::authorize('update', $connection);

        $data = $request->validate([
            'status' => ['required', 'in:draft,proposed,contacted,connected,declined,no_answer,cancelled,closed'],
            'internal_comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $connection->only(['status', 'internal_comment']);
        $dates = match ($data['status']) {
            'contacted' => ['shipper_contacted_at' => now(), 'carrier_contacted_at' => now()],
            'connected' => ['connected_at' => now()],
            'closed' => ['closed_at' => now()],
            default => [],
        };

        $connection->update([...$data, ...$dates]);
        $audit->record('dispatcher_connection.updated', $connection, $old, $connection->only(['status', 'internal_comment']));

        return back()->with('status', 'Статус соединения обновлен.');
    }

    private function dispatcherLoadPayload(FreightLoad $load): array
    {
        return [
            'id' => $load->id,
            'title' => $load->title,
            'status' => $load->status,
            'loading_city' => $load->loading_city,
            'loading_region' => $load->loading_region,
            'loading_address' => $load->loading_address,
            'unloading_city' => $load->unloading_city,
            'unloading_region' => $load->unloading_region,
            'unloading_address' => $load->unloading_address,
            'loading_date' => $this->formatDate($load->loading_date),
            'unloading_date' => $this->formatDate($load->unloading_date),
            'cargo_type' => $load->cargo_type,
            'body_type' => $load->body_type,
            'weight_kg' => $load->weight_kg,
            'volume_m3' => $load->volume_m3,
            'price' => $load->price,
            'is_urgent' => $load->is_urgent,
            'company' => [
                'name' => $load->company?->name,
                'verification_status' => $load->company?->verification_status,
            ],
            'connections_count' => $load->dispatcherConnections->count(),
            'connected_vehicle_ids' => $load->dispatcherConnections
                ->pluck('vehicle_id')
                ->filter()
                ->unique()
                ->values(),
            'urls' => [
                'show' => route('loads.show', $load),
                'map' => route('dispatcher.map', ['load_id' => $load->id]),
            ],
        ];
    }

    private function dispatcherVehiclePayload(Vehicle $vehicle, FreightLoad $load): array
    {
        $company = $vehicle->company ?: $vehicle->carrier?->company;
        $existingConnection = DispatcherConnection::query()
            ->where('load_id', $load->id)
            ->where('vehicle_id', $vehicle->id)
            ->latest()
            ->first();

        return [
            'id' => $vehicle->id,
            'title' => $vehicle->title,
            'body_type' => $vehicle->body_type,
            'capacity_kg' => $vehicle->capacity_kg,
            'volume_m3' => $vehicle->volume_m3 ? (float) $vehicle->volume_m3 : null,
            'current_city' => $vehicle->current_city,
            'current_region' => $vehicle->current_region,
            'is_online' => $vehicle->is_online,
            'last_location_at' => $this->formatDateTime($vehicle->last_location_at),
            'carrier_id' => $vehicle->carrier_id,
            'registration_number' => $vehicle->registration_number,
            'company' => [
                'name' => $company?->name,
                'phone' => $company?->phone,
                'email' => $company?->email,
                'verification_status' => $company?->verification_status,
                'rating' => $company?->rating,
                'reviews_count' => $company?->reviews_count,
            ],
            'driver' => $vehicle->assignedDriver ? [
                'name' => $vehicle->assignedDriver->name,
                'phone' => $vehicle->assignedDriver->phone,
            ] : null,
            'existing_connection' => $existingConnection ? [
                'id' => $existingConnection->id,
                'status' => $existingConnection->status,
                'url' => route('dispatcher.connections.show', $existingConnection),
            ] : null,
            'urls' => [
                'show' => route('vehicles.show', $vehicle),
            ],
        ];
    }

    private function candidateScore(Vehicle $vehicle, FreightLoad $load, float $distanceKm): int
    {
        $score = 100;

        if ($load->body_type && $vehicle->body_type && mb_strtolower($load->body_type) !== mb_strtolower($vehicle->body_type)) {
            $score -= 25;
        }

        if ($load->weight_kg && $vehicle->capacity_kg && $vehicle->capacity_kg < $load->weight_kg) {
            $score -= 35;
        }

        if ($load->volume_m3 && $vehicle->volume_m3 && (float) $vehicle->volume_m3 < (float) $load->volume_m3) {
            $score -= 20;
        }

        if (! $vehicle->is_online) {
            $score -= 10;
        }

        if (($vehicle->company?->verification_status ?: $vehicle->carrier?->company?->verification_status) !== 'verified') {
            $score -= 10;
        }

        $score -= min(20, (int) floor($distanceKm / 100));

        return max(0, $score);
    }

    private function candidateWarnings(Vehicle $vehicle, FreightLoad $load): array
    {
        return collect([
            $load->body_type && $vehicle->body_type && mb_strtolower($load->body_type) !== mb_strtolower($vehicle->body_type)
                ? 'Кузов отличается от требования груза'
                : null,
            $load->weight_kg && $vehicle->capacity_kg && $vehicle->capacity_kg < $load->weight_kg
                ? 'Грузоподъемность ниже веса груза'
                : null,
            $load->volume_m3 && $vehicle->volume_m3 && (float) $vehicle->volume_m3 < (float) $load->volume_m3
                ? 'Объем кузова ниже объема груза'
                : null,
            ! $vehicle->is_online ? 'Машина сейчас не онлайн' : null,
            ($vehicle->company?->verification_status ?: $vehicle->carrier?->company?->verification_status) !== 'verified'
                ? 'Компания не верифицирована'
                : null,
        ])->filter()->values()->all();
    }

    private function formatDate($date): ?string
    {
        return $date ? $date->format('d.m.Y') : null;
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }
}
