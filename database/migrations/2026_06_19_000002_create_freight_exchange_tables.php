<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('inn')->nullable()->index();
            $table->string('kpp')->nullable();
            $table->string('ogrn')->nullable();
            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('verification_status')->default('not_verified')->index();
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->boolean('is_blocked')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete()->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->index();
            $table->string('title');
            $table->string('cargo_type')->nullable();
            $table->text('cargo_description')->nullable();
            $table->string('loading_city')->index();
            $table->string('loading_region')->nullable()->index();
            $table->string('loading_address')->nullable();
            $table->decimal('loading_lat', 10, 7)->nullable()->index();
            $table->decimal('loading_lng', 10, 7)->nullable()->index();
            $table->string('unloading_city')->index();
            $table->string('unloading_region')->nullable()->index();
            $table->string('unloading_address')->nullable();
            $table->decimal('unloading_lat', 10, 7)->nullable()->index();
            $table->decimal('unloading_lng', 10, 7)->nullable()->index();
            $table->date('loading_date')->nullable()->index();
            $table->time('loading_time_from')->nullable();
            $table->time('loading_time_to')->nullable();
            $table->date('unloading_date')->nullable();
            $table->time('unloading_time_from')->nullable();
            $table->time('unloading_time_to')->nullable();
            $table->unsignedInteger('weight_kg')->nullable();
            $table->decimal('volume_m3', 8, 2)->nullable();
            $table->unsignedInteger('places_count')->nullable();
            $table->string('body_type')->nullable()->index();
            $table->string('loading_type')->nullable();
            $table->string('temperature_mode')->nullable();
            $table->unsignedInteger('price')->nullable()->index();
            $table->string('price_currency', 3)->default('RUB');
            $table->boolean('price_with_vat')->default(false);
            $table->string('payment_type')->default('negotiable');
            $table->string('payment_terms')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('bids_count')->default(0);
            $table->boolean('is_urgent')->default(false)->index();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained('users')->cascadeOnDelete()->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->index();
            $table->string('title');
            $table->string('vehicle_type')->nullable();
            $table->string('body_type')->nullable()->index();
            $table->string('registration_number')->nullable();
            $table->string('trailer_number')->nullable();
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->decimal('volume_m3', 8, 2)->nullable();
            $table->decimal('length_m', 6, 2)->nullable();
            $table->decimal('width_m', 6, 2)->nullable();
            $table->decimal('height_m', 6, 2)->nullable();
            $table->string('current_city')->nullable();
            $table->string('current_region')->nullable();
            $table->decimal('current_lat', 10, 7)->nullable()->index();
            $table->decimal('current_lng', 10, 7)->nullable()->index();
            $table->boolean('is_available')->default(false)->index();
            $table->boolean('is_online')->default(false)->index();
            $table->boolean('is_location_visible')->default(false)->index();
            $table->date('available_from_date')->nullable();
            $table->date('available_until_date')->nullable();
            $table->json('preferred_regions')->nullable();
            $table->json('preferred_routes')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('last_location_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_id')->constrained('loads')->cascadeOnDelete()->index();
            $table->foreignId('carrier_id')->constrained('users')->cascadeOnDelete()->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete()->index();
            $table->unsignedInteger('price')->nullable();
            $table->string('price_currency', 3)->default('RUB');
            $table->text('comment')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['load_id', 'carrier_id']);
        });

        Schema::create('dispatcher_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatcher_id')->constrained('users')->cascadeOnDelete()->index();
            $table->foreignId('load_id')->constrained('loads')->cascadeOnDelete()->index();
            $table->foreignId('shipper_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shipper_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->foreignId('carrier_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete()->index();
            $table->foreignId('bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->string('contact_method')->default('platform_notification');
            $table->timestamp('shipper_contacted_at')->nullable();
            $table->timestamp('carrier_contacted_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('summary')->nullable();
            $table->text('internal_comment')->nullable();
            $table->text('shipper_message')->nullable();
            $table->text('carrier_message')->nullable();
            $table->timestamps();
        });

        Schema::create('location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('heading_degrees', 8, 2)->nullable();
            $table->string('source')->default('browser');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('freight_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('message');
            $table->json('data_json')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('load_id')->nullable()->constrained('loads')->nullOnDelete();
            $table->foreignId('bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->foreignId('dispatcher_connection_id')->nullable()->constrained('dispatcher_connections')->nullOnDelete();
            $table->string('type')->default('other');
            $table->text('message');
            $table->string('status')->default('new')->index();
            $table->text('admin_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('action');
            $table->string('entity_type')->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('freight_notifications');
        Schema::dropIfExists('location_pings');
        Schema::dropIfExists('dispatcher_connections');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('loads');
        Schema::dropIfExists('companies');
    }
};
