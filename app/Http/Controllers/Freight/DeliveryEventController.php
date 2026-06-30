<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\DeliveryEvent;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryEventController extends Controller
{
    public function store(Request $request, FreightLoad $load): RedirectResponse
    {
        $load->load(['bids.vehicle']);
        $bid = $this->acceptedBid($load);
        abort_unless($bid && $load->status === 'in_progress', 403);

        $user = $request->user();
        abort_unless($user, 403);

        $allowedTypes = $this->allowedTypes($user, $load, $bid);
        abort_unless($allowedTypes !== [], 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:'.implode(',', $allowedTypes)],
            'note' => ['nullable', 'string', 'max:1000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->ensureEventCanBeStored($bid, $data['type']);

        $event = DB::transaction(function () use ($load, $bid, $user, $data) {
            $event = DeliveryEvent::create([
                'load_id' => $load->id,
                'bid_id' => $bid->id,
                'actor_id' => $user->id,
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
            ]);

            if (in_array($data['type'], DeliveryEvent::CARRIER_EVENT_TYPES, true)) {
                $load->update(['delivery_stage' => $data['type']]);
            }

            $this->updateVehicleLocationFromEvent($bid, $data, $user);

            return $event;
        });

        $this->notifyDeliveryEvent($load, $bid, $event, $user);

        return back()->with('status', 'Статус перевозки обновлен.');
    }

    private function acceptedBid(FreightLoad $load): ?Bid
    {
        return $load->bids->first(fn ($bid) => $bid->status === 'accepted');
    }

    private function allowedTypes(User $user, FreightLoad $load, Bid $bid): array
    {
        if ($bid->canBeOperatedBy($user)) {
            return DeliveryEvent::carrierAvailableEventTypes($load->delivery_stage);
        }

        if ($user->id === $load->shipper_id || $user->isAdmin() || $user->isDispatcher()) {
            return DeliveryEvent::STAFF_EVENT_TYPES;
        }

        return [];
    }

    private function ensureEventCanBeStored(Bid $bid, string $type): void
    {
        if ($type !== 'loaded' || $bid->carrier_cargo_photo_path) {
            return;
        }

        throw ValidationException::withMessages([
            'type' => 'Перед погрузкой загрузите фото груза от перевозчика.',
        ]);
    }

    private function updateVehicleLocationFromEvent(Bid $bid, array $data, User $actor): void
    {
        if (! isset($data['lat'], $data['lng']) || ! $bid->vehicle || ! $bid->canBeOperatedBy($actor)) {
            return;
        }

        $bid->vehicle->update([
            'current_lat' => $data['lat'],
            'current_lng' => $data['lng'],
            'is_online' => true,
            'last_location_at' => now(),
        ]);
    }

    private function notifyDeliveryEvent(FreightLoad $load, Bid $bid, DeliveryEvent $event, User $actor): void
    {
        $recipients = collect([$load->shipper_id, $bid->carrier_id, $bid->vehicle?->assigned_driver_id])
            ->filter()
            ->unique()
            ->reject(fn ($userId) => $userId === $actor->id);

        foreach ($recipients as $userId) {
            FreightNotification::create([
                'user_id' => $userId,
                'type' => 'delivery_event',
                'title' => 'Обновлён этап перевозки',
                'message' => 'По грузу '.$load->title.' зафиксирован новый этап доставки.',
                'data_json' => [
                    'load_id' => $load->id,
                    'bid_id' => $bid->id,
                    'event_id' => $event->id,
                    'action' => $userId === $load->shipper_id ? 'shipper_delivery' : 'delivery',
                ],
            ]);
        }
    }
}
