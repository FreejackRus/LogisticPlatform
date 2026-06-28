<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loads', function (Blueprint $table) {
            if (! Schema::hasColumn('loads', 'cargo_photo_meta')) {
                $table->json('cargo_photo_meta')->nullable();
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'photo_meta')) {
                $table->json('photo_meta')->nullable();
            }
        });

        Schema::table('bids', function (Blueprint $table) {
            if (! Schema::hasColumn('bids', 'carrier_cargo_photo_meta')) {
                $table->json('carrier_cargo_photo_meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            if (Schema::hasColumn('bids', 'carrier_cargo_photo_meta')) {
                $table->dropColumn('carrier_cargo_photo_meta');
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'photo_meta')) {
                $table->dropColumn('photo_meta');
            }
        });

        Schema::table('loads', function (Blueprint $table) {
            if (Schema::hasColumn('loads', 'cargo_photo_meta')) {
                $table->dropColumn('cargo_photo_meta');
            }
        });
    }
};
