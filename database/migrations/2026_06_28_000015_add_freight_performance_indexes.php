<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loads', function (Blueprint $table) {
            $table->index(['status', 'is_urgent', 'published_at'], 'loads_status_urgent_published_idx');
            $table->index(['shipper_id', 'status', 'created_at'], 'loads_shipper_status_created_idx');
            $table->index(['status', 'body_type', 'price'], 'loads_status_body_price_idx');
            $table->index(['status', 'loading_city', 'unloading_city'], 'loads_status_route_idx');
            $table->index(['delivery_stage', 'status'], 'loads_stage_status_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->index(['is_available', 'is_online', 'last_location_at'], 'vehicles_available_online_location_idx');
            $table->index(['is_available', 'body_type', 'capacity_kg'], 'vehicles_available_body_capacity_idx');
            $table->index(['company_id', 'is_available', 'created_at'], 'vehicles_company_available_created_idx');
            $table->index(['assigned_driver_id', 'is_available'], 'vehicles_driver_available_idx');
            $table->index(['carrier_id', 'is_available'], 'vehicles_carrier_available_idx');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->index(['load_id', 'status', 'created_at'], 'bids_load_status_created_idx');
            $table->index(['carrier_id', 'status', 'created_at'], 'bids_carrier_status_created_idx');
            $table->index(['vehicle_id', 'status'], 'bids_vehicle_status_idx');
            $table->index(['company_id', 'status'], 'bids_company_status_idx');
        });

        Schema::table('dispatcher_connections', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'dispatcher_connections_status_created_idx');
            $table->index(['load_id', 'status'], 'dispatcher_connections_load_status_idx');
            $table->index(['carrier_id', 'status'], 'dispatcher_connections_carrier_status_idx');
            $table->index(['bid_id', 'status'], 'dispatcher_connections_bid_status_idx');
        });

        Schema::table('freight_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'is_read', 'created_at'], 'freight_notifications_user_read_created_idx');
            $table->index(['user_id', 'type', 'created_at'], 'freight_notifications_user_type_created_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'complaints_status_created_idx');
            $table->index(['reporter_id', 'status'], 'complaints_reporter_status_idx');
            $table->index(['target_user_id', 'status'], 'complaints_target_status_idx');
            $table->index(['load_id', 'status'], 'complaints_load_status_idx');
        });

        Schema::table('carrier_company_members', function (Blueprint $table) {
            $table->index(['carrier_id', 'status', 'joined_at'], 'carrier_company_members_carrier_status_joined_idx');
            $table->index(['company_id', 'role', 'status'], 'carrier_company_members_company_role_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('carrier_company_members', function (Blueprint $table) {
            $table->dropIndex('carrier_company_members_carrier_status_joined_idx');
            $table->dropIndex('carrier_company_members_company_role_status_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_status_created_idx');
            $table->dropIndex('complaints_reporter_status_idx');
            $table->dropIndex('complaints_target_status_idx');
            $table->dropIndex('complaints_load_status_idx');
        });

        Schema::table('freight_notifications', function (Blueprint $table) {
            $table->dropIndex('freight_notifications_user_read_created_idx');
            $table->dropIndex('freight_notifications_user_type_created_idx');
        });

        Schema::table('dispatcher_connections', function (Blueprint $table) {
            $table->dropIndex('dispatcher_connections_status_created_idx');
            $table->dropIndex('dispatcher_connections_load_status_idx');
            $table->dropIndex('dispatcher_connections_carrier_status_idx');
            $table->dropIndex('dispatcher_connections_bid_status_idx');
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex('bids_load_status_created_idx');
            $table->dropIndex('bids_carrier_status_created_idx');
            $table->dropIndex('bids_vehicle_status_idx');
            $table->dropIndex('bids_company_status_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('vehicles_available_online_location_idx');
            $table->dropIndex('vehicles_available_body_capacity_idx');
            $table->dropIndex('vehicles_company_available_created_idx');
            $table->dropIndex('vehicles_driver_available_idx');
            $table->dropIndex('vehicles_carrier_available_idx');
        });

        Schema::table('loads', function (Blueprint $table) {
            $table->dropIndex('loads_status_urgent_published_idx');
            $table->dropIndex('loads_shipper_status_created_idx');
            $table->dropIndex('loads_status_body_price_idx');
            $table->dropIndex('loads_status_route_idx');
            $table->dropIndex('loads_stage_status_idx');
        });
    }
};
