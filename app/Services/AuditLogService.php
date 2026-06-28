<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function record(string $action, Model $entity, ?array $old = null, ?array $new = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
            'old_values_json' => $old,
            'new_values_json' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
