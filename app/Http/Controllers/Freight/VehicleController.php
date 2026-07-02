<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\LocationPing;
use App\Models\Vehicle;
use App\Services\FreightMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function catalog(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->isCarrier()) {
            return redirect()->route('vehicles.mine');
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'min_capacity' => ['nullable', 'integer', 'min:0'],
            'min_volume' => ['nullable', 'numeric', 'min:0'],
            'online' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,capacity_desc,volume_desc'],
        ]);

        $query = Vehicle::query()
            ->with(['carrier', 'company'])
            ->where('is_available', true);

        if ($filters['q'] ?? null) {
            $search = $filters['q'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('vehicle_type', 'like', '%'.$search.'%')
                    ->orWhere('body_type', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('company', fn ($company) => $company->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($filters['city'] ?? null) {
            $query->where('current_city', 'like', '%'.$filters['city'].'%');
        }

        if ($filters['body_type'] ?? null) {
            $query->where('body_type', $filters['body_type']);
        }

        if ($filters['min_capacity'] ?? null) {
            $query->where('capacity_kg', '>=', $filters['min_capacity']);
        }

        if ($filters['min_volume'] ?? null) {
            $query->where('volume_m3', '>=', $filters['min_volume']);
        }

        if ($request->boolean('online')) {
            $query->where('is_online', true);
        }

        match ($filters['sort'] ?? 'newest') {
            'capacity_desc' => $query->orderByDesc('capacity_kg'),
            'volume_desc' => $query->orderByDesc('volume_m3'),
            default => $query->latest('is_online')->latest('last_location_at')->latest(),
        };

        return Inertia::render('Freight/Vehicles/Catalog', [
            'vehicles' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'filterOptions' => [
                'bodyTypes' => Vehicle::query()
                    ->where('is_available', true)
                    ->whereNotNull('body_type')
                    ->distinct()
                    ->orderBy('body_type')
                    ->pluck('body_type')
                    ->values(),
            ],
            'stats' => [
                'total' => Vehicle::query()->where('is_available', true)->count(),
                'online' => Vehicle::query()->where('is_available', true)->where('is_online', true)->count(),
            ],
            'canSeeContacts' => (bool) $request->user(),
        ]);
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isCarrier(), 403);

        return Inertia::render('Freight/Vehicles/Index', [
            'vehicles' => $this->visibleVehiclesFor($request)->latest()->get()->map(fn (Vehicle $vehicle) => [
                ...$this->vehiclePayload($vehicle, forForm: true),
                'photo_url' => $this->publicUrl($vehicle->photo_path),
                'assigned_driver' => $vehicle->assignedDriver ? [
                    'id' => $vehicle->assignedDriver->id,
                    'name' => $vehicle->assignedDriver->name,
                    'email' => $vehicle->assignedDriver->email,
                ] : null,
                'can_update_location' => $request->user()->can('updateLocation', $vehicle),
            ]),
            'options' => config('freight.options'),
            'drivers' => $this->availableDrivers($request),
            'canCreateVehicle' => (bool) $request->user()->can('create', Vehicle::class),
            'canManageFleet' => $request->user()->canManageCarrierFleet(),
            'canUpdateLocation' => $this->locationVehiclesFor($request)->exists(),
            'isDriverWorkspace' => $request->user()->isCarrierCompanyDriver(),
            'activeCarrierCompany' => $request->user()->activeCarrierCompany() ? [
                'id' => $request->user()->activeCarrierCompany()->id,
                'name' => $request->user()->activeCarrierCompany()->name,
                'carrier_profile_type' => $request->user()->activeCarrierCompany()->carrier_profile_type,
            ] : null,
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        if ($request->user()?->isCarrier()) {
            abort_unless($this->carrierCanAccessVehicle($request, $vehicle), 403);
        }

        $vehicle->load(['carrier', 'company', 'assignedDriver']);

        return Inertia::render('Freight/Vehicles/Show', [
            'vehicle' => [
                ...$this->vehiclePayload($vehicle),
                'photo_url' => $this->publicUrl($vehicle->photo_path),
                'company' => $vehicle->company,
                'carrier' => $vehicle->carrier,
                'assigned_driver' => $vehicle->assignedDriver,
            ],
            'canSeeContacts' => (bool) $request->user(),
        ]);
    }

    public function edit(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize('update', $vehicle);
        $vehicle->load('assignedDriver');

        return Inertia::render('Freight/Vehicles/Edit', [
            'vehicle' => [
                ...$this->vehiclePayload($vehicle),
                'photo_url' => $this->publicUrl($vehicle->photo_path),
                'assigned_driver' => $vehicle->assignedDriver,
            ],
            'options' => config('freight.options'),
            'drivers' => $this->availableDrivers($request),
        ]);
    }

    public function store(Request $request, FreightMediaService $media): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', Vehicle::class);

        $data = $this->validateVehicle($request);
        $photo = $this->storePhoto($request, $media);

        Vehicle::create([
            ...$data,
            'carrier_id' => $user->id,
            'company_id' => $user->activeCarrierCompany()?->id,
            'photo_path' => $photo['path'] ?? null,
            'photo_meta' => $photo['meta'] ?? null,
        ]);

        return back()->with('status', 'Транспорт добавлен.');
    }

    public function update(Request $request, Vehicle $vehicle, FreightMediaService $media): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $data = $this->validateVehicle($request);
        $photo = $this->storePhoto($request, $media, $vehicle->photo_path);

        if ($photo) {
            $data['photo_path'] = $photo['path'];
            $data['photo_meta'] = $photo['meta'];
        }

        $vehicle->update($data);

        return back()->with('status', 'Транспорт обновлен.');
    }

    public function location(Request $request): Response
    {
        abort_unless($request->user()->isCarrier(), 403);

        return Inertia::render('Freight/Vehicles/Location', [
            'vehicles' => $this->locationVehiclesFor($request)->get(),
            'intervalSeconds' => config('freight.location.update_interval_seconds'),
        ]);
    }

    public function updateLocation(Request $request, Vehicle $vehicle): JsonResponse
    {
        Gate::authorize('updateLocation', $vehicle);

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'speed_kmh' => ['nullable', 'numeric', 'min:0'],
            'heading_degrees' => ['nullable', 'numeric', 'min:0', 'max:360'],
        ]);

        $vehicle->update([
            'current_lat' => $data['lat'],
            'current_lng' => $data['lng'],
            'is_online' => true,
            'last_location_at' => now(),
        ]);

        LocationPing::create([
            ...$data,
            'vehicle_id' => $vehicle->id,
            'carrier_id' => $request->user()->id,
            'source' => 'browser',
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function validateVehicle(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'assigned_driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'trailer_number' => ['nullable', 'string', 'max:50'],
            'capacity_kg' => ['nullable', 'integer', 'min:1'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'length_m' => ['nullable', 'numeric', 'min:0'],
            'width_m' => ['nullable', 'numeric', 'min:0'],
            'height_m' => ['nullable', 'numeric', 'min:0'],
            'current_city' => ['nullable', 'string', 'max:255'],
            'current_region' => ['nullable', 'string', 'max:255'],
            'current_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'current_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_available' => ['boolean'],
            'is_location_visible' => ['boolean'],
            'available_from_date' => ['nullable', 'date'],
            'available_until_date' => ['nullable', 'date', 'after_or_equal:available_from_date'],
            'preferred_regions' => ['nullable'],
            'preferred_routes' => ['nullable'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        unset($data['photo']);

        if (! empty($data['assigned_driver_id'])) {
            abort_unless($this->availableDrivers($request)->contains('id', (int) $data['assigned_driver_id']), 422);
        }

        if (isset($data['registration_number'])) {
            $normalizedNumber = preg_replace('/\s+/u', '', mb_strtoupper((string) $data['registration_number']));
            abort_if(
                $normalizedNumber !== '' && (mb_strlen($normalizedNumber) < 6 || mb_strlen($normalizedNumber) > 12),
                422,
                'Госномер должен содержать от 6 до 12 символов.',
            );
            $data['registration_number'] = $normalizedNumber;
        }

        $data['preferred_regions'] = $this->normalizeList($data['preferred_regions'] ?? null);
        $data['preferred_routes'] = $this->normalizeList($data['preferred_routes'] ?? null);

        return $data;
    }

    /**
     * @return array<int, string>|null
     */
    private function normalizeList(mixed $value): ?array
    {
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = preg_split('/[\r\n,]+/', $value) ?: [];
        } else {
            return null;
        }

        $items = array_values(array_filter(array_map(
            fn ($item) => trim((string) $item),
            $items,
        )));

        return $items === [] ? null : $items;
    }

    private function storePhoto(Request $request, FreightMediaService $media, ?string $previousPath = null): ?array
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return $media->storeOptimizedImage($request->file('photo'), 'vehicles', $previousPath);
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    private function vehiclePayload(Vehicle $vehicle, bool $forForm = false): array
    {
        return [
            ...$vehicle->toArray(),
            'available_from_date' => $forForm
                ? $this->formatDateInput($vehicle->available_from_date)
                : $this->formatDate($vehicle->available_from_date),
            'available_until_date' => $forForm
                ? $this->formatDateInput($vehicle->available_until_date)
                : $this->formatDate($vehicle->available_until_date),
            'last_location_at' => $this->formatDateTime($vehicle->last_location_at),
            'created_at' => $this->formatDateTime($vehicle->created_at),
            'updated_at' => $this->formatDateTime($vehicle->updated_at),
        ];
    }

    private function formatDate($date): ?string
    {
        return $date ? $date->format('d.m.Y') : null;
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }

    private function formatDateInput($date): ?string
    {
        return $date ? $date->format('Y-m-d') : null;
    }

    private function visibleVehiclesFor(Request $request)
    {
        $user = $request->user();

        return Vehicle::query()
            ->with('assignedDriver')
            ->where(fn ($query) => $this->scopeCarrierVehicles($query, $user));
    }

    private function locationVehiclesFor(Request $request)
    {
        $user = $request->user();

        return Vehicle::query()
            ->where(fn ($query) => $query
                ->where('carrier_id', $user->id)
                ->orWhere('assigned_driver_id', $user->id));
    }

    private function carrierCanAccessVehicle(Request $request, Vehicle $vehicle): bool
    {
        $user = $request->user();

        return Vehicle::query()
            ->whereKey($vehicle->id)
            ->where(fn ($query) => $this->scopeCarrierVehicles($query, $user))
            ->exists();
    }

    private function scopeCarrierVehicles($query, $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->where('carrier_id', $user->id)
                ->orWhere('assigned_driver_id', $user->id);

            return;
        }

        $query->where('carrier_id', $user->id);

        if ($companyId = $user->activeCarrierCompany()?->id) {
            $query->orWhere('company_id', $companyId);
        }
    }

    private function availableDrivers(Request $request)
    {
        if (! $request->user()->canManageCarrierFleet()) {
            return collect();
        }

        $company = $request->user()->activeCarrierCompany();

        if (! $company || $company->type !== 'carrier' || $company->carrier_profile_type !== 'company') {
            return collect();
        }

        return $company->carrierMembers()
            ->wherePivot('status', 'active')
            ->wherePivot('role', 'driver')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']);
    }
}
