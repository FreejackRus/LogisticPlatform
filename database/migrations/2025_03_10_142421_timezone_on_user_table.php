<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('timezones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->nullable()->constrained('timezones', 'name');
        });

        DB::table('timezones')->insert([
            ['name' => 'America/New_York'],
            ['name' => 'America/Chicago'],
            ['name' => 'America/Denver'],
            ['name' => 'America/Los_Angeles'],
            ['name' => 'America/Honolulu'],
            ['name' => 'America/Anchorage'],
        ]);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });

        Schema::dropIfExists('timezones');
    }
};
