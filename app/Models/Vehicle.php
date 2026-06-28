<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrier_id',
        'assigned_driver_id',
        'company_id',
        'title',
        'vehicle_type',
        'body_type',
        'registration_number',
        'trailer_number',
        'capacity_kg',
        'volume_m3',
        'length_m',
        'width_m',
        'height_m',
        'current_city',
        'current_region',
        'current_lat',
        'current_lng',
        'is_available',
        'is_online',
        'is_location_visible',
        'available_from_date',
        'available_until_date',
        'preferred_regions',
        'preferred_routes',
        'description',
        'photo_path',
        'photo_meta',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'volume_m3' => 'decimal:2',
            'length_m' => 'decimal:2',
            'width_m' => 'decimal:2',
            'height_m' => 'decimal:2',
            'current_lat' => 'decimal:7',
            'current_lng' => 'decimal:7',
            'is_available' => 'boolean',
            'is_online' => 'boolean',
            'is_location_visible' => 'boolean',
            'available_from_date' => 'date',
            'available_until_date' => 'date',
            'preferred_regions' => 'array',
            'preferred_routes' => 'array',
            'photo_meta' => 'array',
            'last_location_at' => 'datetime',
        ];
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(LocationPing::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function scopeVisibleOnMap(Builder $query): Builder
    {
        return $query
            ->where('is_available', true)
            ->where('is_location_visible', true)
            ->whereNotNull('current_lat')
            ->whereNotNull('current_lng');
    }

    public function scopeEligibleForLoad(Builder $query, FreightLoad $load): Builder
    {
        $query
            ->where('is_available', true)
            ->whereDoesntHave('bids', fn (Builder $bidQuery) => $bidQuery
                ->where('status', 'accepted')
                ->whereHas('freightLoad', fn (Builder $loadQuery) => $loadQuery
                    ->where('status', 'in_progress')
                    ->whereKeyNot($load->id)
                )
            );

        if ($load->body_type) {
            $query->where('body_type', $load->body_type);
        }

        if ($load->weight_kg) {
            $query->whereNotNull('capacity_kg')->where('capacity_kg', '>=', $load->weight_kg);
        }

        if ($load->volume_m3) {
            $query->whereNotNull('volume_m3')->where('volume_m3', '>=', $load->volume_m3);
        }

        if ($load->loading_date) {
            $query->where(fn (Builder $dateQuery) => $dateQuery
                ->whereNull('available_from_date')
                ->orWhereDate('available_from_date', '<=', $load->loading_date)
            );
        }

        if ($load->unloading_date ?: $load->loading_date) {
            $finishDate = $load->unloading_date ?: $load->loading_date;

            $query->where(fn (Builder $dateQuery) => $dateQuery
                ->whereNull('available_until_date')
                ->orWhereDate('available_until_date', '>=', $finishDate)
            );
        }

        return $query;
    }

    public function compatibilityErrorsForLoad(FreightLoad $load): array
    {
        $errors = [];

        if (! $this->is_available) {
            $errors[] = 'Транспорт сейчас не отмечен как доступный.';
        }

        if ($this->hasActiveDelivery($load)) {
            $errors[] = 'Транспорт уже назначен на активную перевозку.';
        }

        if ($load->body_type && $this->body_type !== $load->body_type) {
            $errors[] = 'Тип кузова транспорта не подходит для груза.';
        }

        if ($load->weight_kg && (! $this->capacity_kg || $this->capacity_kg < $load->weight_kg)) {
            $errors[] = 'Грузоподъемность транспорта меньше веса груза.';
        }

        if ($load->volume_m3 && (! $this->volume_m3 || (float) $this->volume_m3 < (float) $load->volume_m3)) {
            $errors[] = 'Объем кузова меньше объема груза.';
        }

        if ($load->loading_date && $this->available_from_date && $this->available_from_date->gt($load->loading_date)) {
            $errors[] = 'Транспорт будет доступен позже даты погрузки.';
        }

        $finishDate = $load->unloading_date ?: $load->loading_date;
        if ($finishDate && $this->available_until_date && $this->available_until_date->lt($finishDate)) {
            $errors[] = 'Период доступности транспорта заканчивается раньше завершения рейса.';
        }

        return $errors;
    }

    public function hasActiveDelivery(?FreightLoad $exceptLoad = null): bool
    {
        return $this->bids()
            ->where('status', 'accepted')
            ->whereHas('freightLoad', function (Builder $query) use ($exceptLoad) {
                $query->where('status', 'in_progress');

                if ($exceptLoad) {
                    $query->whereKeyNot($exceptLoad->id);
                }
            })
            ->exists();
    }
}
