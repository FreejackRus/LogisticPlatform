<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatcherConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispatcher_id',
        'load_id',
        'shipper_id',
        'shipper_company_id',
        'carrier_id',
        'carrier_company_id',
        'vehicle_id',
        'bid_id',
        'status',
        'contact_method',
        'shipper_contacted_at',
        'carrier_contacted_at',
        'connected_at',
        'closed_at',
        'summary',
        'internal_comment',
        'shipper_message',
        'carrier_message',
    ];

    protected function casts(): array
    {
        return [
            'shipper_contacted_at' => 'datetime',
            'carrier_contacted_at' => 'datetime',
            'connected_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatcher_id');
    }

    public function freightLoad(): BelongsTo
    {
        return $this->belongsTo(FreightLoad::class, 'load_id');
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }
}
