<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPing extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id',
        'carrier_id',
        'lat',
        'lng',
        'accuracy_meters',
        'speed_kmh',
        'heading_degrees',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
            'speed_kmh' => 'decimal:2',
            'heading_degrees' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_id');
    }
}
