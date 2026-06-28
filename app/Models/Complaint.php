<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'target_user_id',
        'load_id',
        'bid_id',
        'dispatcher_connection_id',
        'type',
        'message',
        'status',
        'admin_comment',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function freightLoad(): BelongsTo
    {
        return $this->belongsTo(FreightLoad::class, 'load_id');
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function dispatcherConnection(): BelongsTo
    {
        return $this->belongsTo(DispatcherConnection::class);
    }
}
