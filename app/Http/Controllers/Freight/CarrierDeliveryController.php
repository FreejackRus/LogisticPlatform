<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\DeliveryEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CarrierDeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->validate([
            'status' => ['nullable', 'in:active,completed,all'],
        ])['status'] ?? 'active';

        $query = Bid::query()
            ->with([
                'vehicle.assignedDriver',
                'freightLoad.company',
                'freightLoad.deliveryEvents.actor',
            ])
            ->where(fn ($builder) => $this->scopeDeliveriesForUser($builder, $request->user()))
            ->where('status', 'accepted')
            ->whereHas('freightLoad', function ($builder) use ($filter) {
                match ($filter) {
                    'completed' => $builder->where('status', 'completed'),
                    'all' => $builder->whereIn('status', ['in_progress', 'completed']),
                    default => $builder->where('status', 'in_progress'),
                };
            })
            ->latest('accepted_at')
            ->latest();

        return Inertia::render('Freight/Carrier/Deliveries', [
            'deliveries' => $query->get()->map(fn (Bid $bid) => $this->deliveryPayload($bid, $request->user())),
            'filters' => ['status' => $filter],
            'stats' => [
                'active' => $this->countByLoadStatus($request, ['in_progress']),
                'completed' => $this->countByLoadStatus($request, ['completed']),
            ],
        ]);
    }

    public function show(Request $request, Bid $bid): Response
    {
        $bid->load([
            'vehicle.assignedDriver',
            'freightLoad.company',
            'freightLoad.deliveryEvents.actor',
        ]);

        abort_unless($bid->status === 'accepted' && $this->canViewDelivery($request->user(), $bid), 403);

        abort_unless(in_array($bid->freightLoad?->status, ['in_progress', 'completed'], true), 404);

        return Inertia::render('Freight/Carrier/DeliveryShow', [
            'delivery' => $this->deliveryPayload($bid, $request->user()),
        ]);
    }

    private function deliveryPayload(Bid $bid, ?User $user): array
    {
        $load = $bid->freightLoad;
        $latestEvent = $load->deliveryEvents->sortByDesc('id')->first();
        $canOperateDelivery = $user
            && $load->status === 'in_progress'
            && $bid->canBeOperatedBy($user);

        return [
            'bid_id' => $bid->id,
            'load' => [
                'id' => $load->id,
                'title' => $load->title,
                'status' => $load->status,
                'delivery_stage' => $load->delivery_stage,
                'loading_city' => $load->loading_city,
                'unloading_city' => $load->unloading_city,
                'loading_date' => $load->loading_date?->format('d.m.Y'),
                'unloading_date' => $load->unloading_date?->format('d.m.Y'),
                'loading_region' => $load->loading_region,
                'loading_address' => $load->loading_address,
                'unloading_region' => $load->unloading_region,
                'unloading_address' => $load->unloading_address,
                'cargo_type' => $load->cargo_type,
                'cargo_description' => $load->cargo_description,
                'body_type' => $load->body_type,
                'weight_kg' => $load->weight_kg,
                'volume_m3' => $load->volume_m3,
                'places_count' => $load->places_count,
                'loading_type' => $load->loading_type,
                'temperature_mode' => $load->temperature_mode,
                'price' => $load->price,
                'payment_type' => $load->payment_type,
                'payment_terms' => $load->payment_terms,
                'contact_name' => $load->contact_name ?: $load->company?->contact_person ?: $load->company?->name,
                'contact_phone' => $load->contact_phone ?: $load->company?->phone,
                'contact_email' => $load->contact_email ?: $load->company?->email,
                'cargo_photo_url' => $this->publicUrl($load->cargo_photo_path),
                'delivery_confirmation' => [
                    'code' => $load->delivery_confirmation_code,
                    'url' => route('loads.show', ['load' => $load->id, 'confirm' => $load->delivery_confirmation_token]),
                    'confirmed_at' => $this->formatDateTime($load->completion_confirmed_at),
                ],
                'carrier_delivery_url' => route('carrier.deliveries.show', $bid),
                'url' => route('loads.show', $load),
                'contract_url' => route('loads.contract', $load),
                'route_url' => route('map', ['load_id' => $load->id, 'route' => 1]),
                'event_url' => route('loads.delivery-events.store', $load),
                'next_delivery_event' => $canOperateDelivery
                    ? DeliveryEvent::nextCarrierEvent($load->delivery_stage)
                    : null,
            ],
            'vehicle' => $bid->vehicle ? [
                'id' => $bid->vehicle->id,
                'title' => $bid->vehicle->title,
                'registration_number' => $bid->vehicle->registration_number,
                'assigned_driver' => $bid->vehicle->assignedDriver ? [
                    'id' => $bid->vehicle->assignedDriver->id,
                    'name' => $bid->vehicle->assignedDriver->name,
                    'email' => $bid->vehicle->assignedDriver->email,
                ] : null,
            ] : null,
            'carrier_cargo_photo_url' => $this->publicUrl($bid->carrier_cargo_photo_path),
            'carrier_photo_url' => route('bids.carrier-photo', $bid),
            'can_update_delivery' => (bool) $canOperateDelivery,
            'can_upload_carrier_cargo_photo' => (bool) $canOperateDelivery,
            'delivery_event_options' => $canOperateDelivery
                ? DeliveryEvent::carrierAvailableEventTypes($load->delivery_stage)
                : [],
            'next_delivery_event' => $canOperateDelivery
                ? DeliveryEvent::nextCarrierEvent($load->delivery_stage)
                : null,
            'latest_event' => $latestEvent ? [
                'type' => $latestEvent->type,
                'note' => $latestEvent->note,
                'created_at' => $this->formatDateTime($latestEvent->created_at),
                'actor' => [
                    'name' => $latestEvent->actor?->name,
                    'role' => $latestEvent->actor?->role,
                ],
            ] : null,
            'events' => $load->deliveryEvents
                ->sortByDesc('id')
                ->map(fn (DeliveryEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'note' => $event->note,
                        'created_at' => $this->formatDateTime($event->created_at),
                    'actor' => [
                        'name' => $event->actor?->name,
                        'role' => $event->actor?->role,
                    ],
                ])
                ->values(),
        ];
    }

    private function countByLoadStatus(Request $request, array $statuses): int
    {
        return Bid::query()
            ->where(fn ($builder) => $this->scopeDeliveriesForUser($builder, $request->user()))
            ->where('status', 'accepted')
            ->whereHas('freightLoad', fn ($builder) => $builder->whereIn('status', $statuses))
            ->count();
    }

    private function scopeDeliveriesForUser($query, $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->whereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id))
                ->orWhere('carrier_id', $user->id);

            return;
        }

        $companyId = $user->activeCarrierCompany()?->id;

        $query->where('carrier_id', $user->id);

        if ($companyId) {
            $query->orWhere('company_id', $companyId);
        }
    }

    private function canViewDelivery($user, Bid $bid): bool
    {
        if ($bid->carrier_id === $user->id || $bid->vehicle?->assigned_driver_id === $user->id) {
            return true;
        }

        return ! $user->isCarrierCompanyDriver()
            && $user->activeCarrierCompany()?->id
            && $bid->company_id === $user->activeCarrierCompany()->id;
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }
}
