<?php

namespace App\Console\Commands;

use App\Models\LocationPing;
use Illuminate\Console\Command;

class PruneLocationPings extends Command
{
    protected $signature = 'location-pings:prune {--days=}';

    protected $description = 'Delete old vehicle location pings.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('freight.location.retention_days', 30));
        $count = LocationPing::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$count} location pings older than {$days} days.");

        return self::SUCCESS;
    }
}
