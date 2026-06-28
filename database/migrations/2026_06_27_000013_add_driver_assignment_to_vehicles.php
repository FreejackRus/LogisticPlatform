<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'assigned_driver_id')) {
                $table->foreignId('assigned_driver_id')
                    ->nullable()
                    ->after('carrier_id')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'assigned_driver_id')) {
                $table->dropConstrainedForeignId('assigned_driver_id');
            }
        });
    }
};
