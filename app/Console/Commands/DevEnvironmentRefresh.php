<?php

namespace App\Console\Commands;

use Database\Seeders\DemoOrgSeeder;
use Database\Seeders\FreightExchangeSeeder;
use Illuminate\Console\Command;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\clear;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

class DevEnvironmentRefresh extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the database with demo data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        warning("This will refresh the database with demo data, all current data will be lost.");
        $confirmed = confirm("Continue?", default: false);
        if (!$confirmed) {
            alert("✗ Refresh cancelled");
            return;
        }
        

        $progress = progress(label: "Refreshing database", steps: 3);

        $progress->start();

        $progress->hint("Migrating database");
        $this->call('migrate:fresh', []);
        $progress->advance();

        $progress->hint("Seeding demo data");
        (new DemoOrgSeeder())->run();
        $progress->advance();

        $progress->hint("Seeding freight exchange data");
        (new FreightExchangeSeeder())->run();
        $progress->advance();

        $progress->finish();
        info("✓ Refresh complete");
    }
}
