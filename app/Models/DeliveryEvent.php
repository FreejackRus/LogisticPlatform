<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryEvent extends Model
{
    use HasFactory;

    public const CARRIER_EVENT_TYPES = [
        'en_route_to_pickup',
        'arrived_pickup',
        'loaded',
        'in_transit',
        'arrived_unloading',
        'delivered_pending_confirmation',
    ];

    public const STAFF_EVENT_TYPES = [
        'shipper_note',
        'issue_reported',
    ];

    public const SYSTEM_EVENT_TYPES = [
        'carrier_selected',
        'delivery_confirmed',
    ];

    public const DELIVERY_CONFIRMATION_STAGE = 'delivered_pending_confirmation';

    public const CANCELLABLE_DELIVERY_STAGES = [
        'carrier_selected',
        'en_route_to_pickup',
        'arrived_pickup',
    ];

    public static function nextCarrierEvent(?string $currentStage): ?string
    {
        $index = array_search($currentStage, self::CARRIER_EVENT_TYPES, true);

        if ($index === false) {
            return self::CARRIER_EVENT_TYPES[0];
        }

        return self::CARRIER_EVENT_TYPES[$index + 1] ?? null;
    }

    public static function carrierAvailableEventTypes(?string $currentStage): array
    {
        return array_values(array_filter([
            self::nextCarrierEvent($currentStage),
            'issue_reported',
        ]));
    }

    public static function canConfirmDelivery(?string $currentStage): bool
    {
        return $currentStage === self::DELIVERY_CONFIRMATION_STAGE;
    }

    public static function canCancelDelivery(?string $currentStage): bool
    {
        return in_array($currentStage, self::CANCELLABLE_DELIVERY_STAGES, true);
    }

    protected $fillable = [
        'load_id',
        'bid_id',
        'actor_id',
        'type',
        'note',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function freightLoad(): BelongsTo
    {
        return $this->belongsTo(FreightLoad::class, 'load_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
