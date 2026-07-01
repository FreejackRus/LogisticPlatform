<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'load_id',
        'carrier_id',
        'company_id',
        'vehicle_id',
        'price',
        'price_currency',
        'comment',
        'status',
        'contract_accepted_at',
        'contract_signed_at',
        'contract_terms_version',
        'carrier_cargo_photo_path',
        'carrier_cargo_photo_meta',
        'accepted_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'contract_accepted_at' => 'datetime',
            'contract_signed_at' => 'datetime',
            'carrier_cargo_photo_meta' => 'array',
        ];
    }

    public function freightLoad(): BelongsTo
    {
        return $this->belongsTo(FreightLoad::class, 'load_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function canBeOperatedBy(User $user): bool
    {
        if ($this->vehicle?->assigned_driver_id) {
            return $this->vehicle->assigned_driver_id === $user->id;
        }

        return $this->carrier_id === $user->id;
    }

    public function notificationRecipientIds(): Collection
    {
        $this->loadMissing(['company', 'vehicle']);

        $ids = collect([
            $this->carrier_id,
            $this->vehicle?->assigned_driver_id,
            $this->company?->user_id,
        ]);

        if ($this->company) {
            $ids = $ids->merge(
                $this->company->carrierMembers()
                    ->wherePivot('status', 'active')
                    ->wherePivot('role', 'manager')
                    ->pluck('users.id'),
            );
        }

        return $ids->filter()->unique()->values();
    }
}
