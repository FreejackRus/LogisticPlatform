<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreightLoad extends Model
{
    use HasFactory;

    protected $table = 'loads';

    protected $fillable = [
        'shipper_id',
        'company_id',
        'title',
        'cargo_type',
        'cargo_description',
        'loading_city',
        'loading_region',
        'loading_address',
        'loading_lat',
        'loading_lng',
        'unloading_city',
        'unloading_region',
        'unloading_address',
        'unloading_lat',
        'unloading_lng',
        'loading_date',
        'loading_time_from',
        'loading_time_to',
        'unloading_date',
        'unloading_time_from',
        'unloading_time_to',
        'weight_kg',
        'volume_m3',
        'places_count',
        'body_type',
        'loading_type',
        'temperature_mode',
        'price',
        'price_currency',
        'price_with_vat',
        'payment_type',
        'payment_terms',
        'contact_name',
        'contact_phone',
        'contact_email',
        'cargo_photo_path',
        'cargo_photo_meta',
        'delivery_confirmation_token',
        'delivery_confirmation_code',
        'status',
        'delivery_stage',
        'views_count',
        'bids_count',
        'is_urgent',
        'is_featured',
        'published_at',
        'completed_at',
        'cancelled_at',
        'completion_confirmed_at',
        'completion_confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'loading_date' => 'date',
            'unloading_date' => 'date',
            'loading_lat' => 'decimal:7',
            'loading_lng' => 'decimal:7',
            'unloading_lat' => 'decimal:7',
            'unloading_lng' => 'decimal:7',
            'volume_m3' => 'decimal:2',
            'price_with_vat' => 'boolean',
            'cargo_photo_meta' => 'array',
            'is_urgent' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completion_confirmed_at' => 'datetime',
        ];
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'load_id');
    }

    public function dispatcherConnections(): HasMany
    {
        return $this->hasMany(DispatcherConnection::class, 'load_id');
    }

    public function deliveryEvents(): HasMany
    {
        return $this->hasMany(DeliveryEvent::class, 'load_id');
    }

    public function completionConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completion_confirmed_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
