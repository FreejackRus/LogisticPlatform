<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreightNotification extends Model
{
    use HasFactory;

    protected $table = 'freight_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data_json',
        'is_read',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data_json' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
