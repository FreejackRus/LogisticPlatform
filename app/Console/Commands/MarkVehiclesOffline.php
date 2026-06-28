<?php

namespace App\Console\Commands;

use App\Services\VehicleOnlineStatusService;
use Illuminate\Console\Command;

class MarkVehiclesOffline extends Command
{
    protected $signature = 'vehicles:mark-offline';

    protected $description = 'Mark vehicles offline when their location is stale.';

    public function handle(VehicleOnlineStatusService $service): int
    {
        $count = $service->markOffline();
        $this->info("Marked {$count} vehicles offline.");

        return self::SUCCESS;
    }
}
