<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\DeliveryEvent;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\Vehicle;
use App\Services\AuditLogService;
use App\Services\FreightMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BidController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $status = $request->validate([
            'status' => ['nullable', 'in:all,pending,accepted,rejected,cancelled'],
        ])['status'] ?? 'all';

        $baseQuery = Bid::query()
            ->where(fn ($query) => $this->scopeBidsForUser($query, $user));

        $bidsQuery = (clone $baseQuery)
            ->with(['freightLoad.company', 'vehicle.assignedDriver', 'company', 'carrier'])
            ->latest();

        if ($status !== 'all') {
            $bidsQuery->where('status', $status);
        }

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Freight/Carrier/Bids', [
            'bids' => $bidsQuery->paginate(12)->withQueryString()->through(
                fn (Bid $bid) => $this->bidPayload($bid, $user),
            ),
            'currentStatus' => $status,
            'statusCounts' => [
                'all' => (clone $baseQuery)->count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'accepted' => (int) ($statusCounts['accepted'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            ],
        ]);
    }

    public function store(Request $request, FreightLoad $load, FreightMediaService $media): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('respond', $load);

        $data = $request->validate([
            'vehicle_id' => ['nullable', 'exists:vehicles,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'contract_accepted' => ['accepted'],
            'carrier_cargo_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (! empty($data['vehicle_id'])) {
            Gate::authorize('useForBid', Vehicle::findOrFail($data['vehicle_id']));
        }

        $contractAccepted = $data['contract_accepted'] ?? false;
        unset($data['contract_accepted']);

        $carrierCargoPhoto = $request->hasFile('carrier_cargo_photo')
            ? $media->storeOptimizedImage($request->file('carrier_cargo_photo'), 'bid-cargo')
            : null;
        unset($data['carrier_cargo_photo']);

        $existingBid = Bid::where('load_id', $load->id)
            ->where('carrier_id', $user->id)
            ->first();

        abort_if($existingBid && in_array($existingBid->status, ['pending', 'accepted'], true), 422, 'У вас уже есть активный отклик на этот груз.');

        $bid = Bid::updateOrCreate([
            'load_id' => $load->id,
            'carrier_id' => $user->id,
        ], [
            ...$data,
            'company_id' => $user->activeCarrierCompany()?->id,
            'price_currency' => 'RUB',
            'status' => 'pending',
            'contract_accepted_at' => $contractAccepted ? now() : null,
            'contract_terms_version' => config('freight.contracts.terms_version') ?: '2026-06',
            'carrier_cargo_photo_path' => $carrierCargoPhoto['path'] ?? null,
            'carrier_cargo_photo_meta' => $carrierCargoPhoto['meta'] ?? null,
            'cancelled_at' => null,
            'rejected_at' => null,
        ]);

        $load->update(['bids_count' => $load->bids()->whereIn('status', ['pending', 'accepted'])->count()]);
        $this->attachDispatcherConnection($bid, $load, $user, $data['vehicle_id'] ?? null);

        FreightNotification::create([
            'user_id' => $load->shipper_id,
            'type' => 'bid_created',
            'title' => 'Новый отклик на ваш груз',
            'message' => 'Перевозчик '.($user->company?->name ?? $user->name).' откликнулся на груз '.$load->title.'.',
            'data_json' => ['bid_id' => $bid->id, 'load_id' => $load->id, 'action' => 'bids'],
        ]);

        return back()->with('status', 'Отклик отправлен.');
    }

    private function attachDispatcherConnection(Bid $bid, FreightLoad $load, $user, mixed $vehicleId): void
    {
        $connection = DispatcherConnection::query()
            ->where('load_id', $load->id)
            ->whereNull('bid_id')
            ->whereIn('status', ['proposed', 'contacted', 'connected'])
            ->where(function ($query) use ($user, $vehicleId) {
                $query->where('carrier_id', $user->id);

                if ($vehicleId) {
                    $query->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->latest()
            ->first();

        if (! $connection) {
            return;
        }

        $connection->update([
            'bid_id' => $bid->id,
            'status' => 'connected',
            'connected_at' => $connection->connected_at ?? now(),
        ]);

        FreightNotification::create([
            'user_id' => $connection->dispatcher_id,
            'type' => 'dispatcher_connection',
            'title' => 'Перевозчик откликнулся по подбору',
            'message' => 'По диспетчерскому подбору к грузу '.$load->title.' появился отклик перевозчика.',
            'data_json' => ['dispatcher_connection_id' => $connection->id, 'bid_id' => $bid->id, 'load_id' => $load->id],
        ]);
    }

    public function accept(Request $request, Bid $bid, AuditLogService $audit): RedirectResponse
    {
        $bid->loadMissing(['freightLoad', 'carrier', 'vehicle.assignedDriver']);
        Gate::authorize('accept', $bid);

        DB::transaction(function () use ($request, $bid, $audit) {
            $rejectedBids = $bid->freightLoad->bids()
                ->with('vehicle.assignedDriver')
                ->whereKeyNot($bid->id)
                ->where('status', 'pending')
                ->get();

            $bid->freightLoad->bids()
                ->whereKey($rejectedBids->pluck('id'))
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                ]);

            $bid->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'contract_signed_at' => now(),
            ]);
            $bid->freightLoad->update([
                'status' => 'in_progress',
                'delivery_stage' => 'carrier_selected',
            ]);

            DeliveryEvent::create([
                'load_id' => $bid->load_id,
                'bid_id' => $bid->id,
                'actor_id' => $request->user()?->id,
                'type' => 'carrier_selected',
            ]);

            FreightNotification::create([
                'user_id' => $bid->carrier_id,
                'type' => 'bid_accepted',
                'title' => 'Ваш отклик принят',
                'message' => 'Грузовладелец выбрал ваш отклик на груз '.$bid->freightLoad->title.'.',
                'data_json' => ['bid_id' => $bid->id, 'load_id' => $bid->load_id, 'action' => 'delivery'],
            ]);

            if ($bid->vehicle?->assigned_driver_id && $bid->vehicle->assigned_driver_id !== $bid->carrier_id) {
                FreightNotification::create([
                    'user_id' => $bid->vehicle->assigned_driver_id,
                    'type' => 'bid_accepted',
                    'title' => 'Назначен рейс',
                    'message' => 'Вас назначили водителем на груз '.$bid->freightLoad->title.'.',
                    'data_json' => ['bid_id' => $bid->id, 'load_id' => $bid->load_id, 'action' => 'delivery'],
                ]);
            }

            $rejectedBids->each(function (Bid $rejectedBid) use ($bid) {
                collect([$rejectedBid->carrier_id, $rejectedBid->vehicle?->assigned_driver_id])
                    ->filter()
                    ->unique()
                    ->each(fn ($userId) => FreightNotification::create([
                        'user_id' => $userId,
                        'type' => 'bid_rejected',
                        'title' => 'Выбран другой перевозчик',
                        'message' => 'По грузу '.$bid->freightLoad->title.' заказчик выбрал другой отклик.',
                        'data_json' => ['bid_id' => $rejectedBid->id, 'load_id' => $bid->load_id, 'action' => 'bid'],
                    ]));
            });

            $audit->record('bid.accepted', $bid, null, ['status' => 'accepted']);
        });

        return back()->with('status', 'Отклик принят.');
    }

    public function cancel(Request $request, Bid $bid): RedirectResponse
    {
        Gate::authorize('cancel', $bid);

        $bid->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $bid->freightLoad()->update([
            'bids_count' => $bid->freightLoad->bids()->whereIn('status', ['pending', 'accepted'])->count(),
        ]);

        FreightNotification::create([
            'user_id' => $bid->freightLoad->shipper_id,
            'type' => 'bid_cancelled',
            'title' => 'Отклик отменён',
            'message' => 'Перевозчик отменил отклик на груз '.$bid->freightLoad->title.'.',
            'data_json' => ['bid_id' => $bid->id, 'load_id' => $bid->load_id, 'action' => 'bids'],
        ]);

        return back()->with('status', 'Отклик отменен.');
    }

    public function uploadCarrierCargoPhoto(Request $request, Bid $bid, FreightMediaService $media): RedirectResponse
    {
        $bid->loadMissing('vehicle');

        abort_unless(
            $bid->status === 'accepted'
                && ($request->user()?->id === $bid->carrier_id || $request->user()?->id === $bid->vehicle?->assigned_driver_id),
            403,
        );

        $data = $request->validate([
            'carrier_cargo_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $carrierCargoPhoto = $media->storeOptimizedImage(
            $data['carrier_cargo_photo'],
            'bid-cargo',
            $bid->carrier_cargo_photo_path,
        );

        $bid->update([
            'carrier_cargo_photo_path' => $carrierCargoPhoto['path'],
            'carrier_cargo_photo_meta' => $carrierCargoPhoto['meta'],
        ]);

        return back()->with('status', 'Фото груза от перевозчика загружено.');
    }

    private function scopeBidsForUser($query, $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->where('carrier_id', $user->id)
                ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id));

            return;
        }

        $query->where('carrier_id', $user->id);

        if ($companyId = $user->activeCarrierCompany()?->id) {
            $query->orWhere('company_id', $companyId);
        }
    }

    private function bidPayload(Bid $bid, $user): array
    {
        $load = $bid->freightLoad;

        return [
            'id' => $bid->id,
            'status' => $bid->status,
            'comment' => $bid->comment,
            'created_at' => $this->formatDateTime($bid->created_at),
            'accepted_at' => $this->formatDateTime($bid->accepted_at),
            'rejected_at' => $this->formatDateTime($bid->rejected_at),
            'cancelled_at' => $this->formatDateTime($bid->cancelled_at),
            'contract_accepted_at' => $this->formatDateTime($bid->contract_accepted_at),
            'contract_signed_at' => $this->formatDateTime($bid->contract_signed_at),
            'can_cancel' => $user->can('cancel', $bid),
            'load' => [
                'id' => $load->id,
                'title' => $load->title,
                'status' => $load->status,
                'delivery_stage' => $load->delivery_stage,
                'loading_city' => $load->loading_city,
                'unloading_city' => $load->unloading_city,
                'loading_date' => $load->loading_date?->format('d.m.Y'),
                'unloading_date' => $load->unloading_date?->format('d.m.Y'),
                'price' => $load->price,
                'price_currency' => $load->price_currency,
                'body_type' => $load->body_type,
                'cargo_type' => $load->cargo_type,
                'company_name' => $load->company?->name,
                'url' => route('loads.show', $load),
                'delivery_url' => $bid->status === 'accepted' && in_array($load->status, ['in_progress', 'completed'], true)
                    ? route('carrier.deliveries.show', $bid)
                    : null,
                'contract_url' => $bid->status === 'accepted'
                    ? route('loads.contract', $load)
                    : null,
            ],
            'vehicle' => $bid->vehicle ? [
                'id' => $bid->vehicle->id,
                'title' => $bid->vehicle->title,
                'registration_number' => $bid->vehicle->registration_number,
                'assigned_driver' => $bid->vehicle->assignedDriver ? [
                    'id' => $bid->vehicle->assignedDriver->id,
                    'name' => $bid->vehicle->assignedDriver->name,
                ] : null,
            ] : null,
            'company' => $bid->company ? [
                'id' => $bid->company->id,
                'name' => $bid->company->name,
            ] : null,
            'carrier' => [
                'id' => $bid->carrier?->id,
                'name' => $bid->carrier?->name,
            ],
        ];
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }
}
