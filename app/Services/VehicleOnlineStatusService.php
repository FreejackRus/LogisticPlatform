<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Support\Carbon;

class VehicleOnlineStatusService
{
    public function markOffline(?Carbon $now = null): int
    {
        $threshold = ($now ?? now())->subMinutes(config('freight.location.online_timeout_minutes', 5));

        return Vehicle::query()
            ->where('is_online', true)
            ->where(function ($query) use ($threshold) {
                $query->whereNull('last_location_at')
                    ->orWhere('last_location_at', '<', $threshold);
            })
            ->update(['is_online' => false]);
    }
}
