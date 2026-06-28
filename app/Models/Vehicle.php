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
}
