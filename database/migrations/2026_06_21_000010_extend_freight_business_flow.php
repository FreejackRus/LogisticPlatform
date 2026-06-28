<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                $table->timestamp('terms_accepted_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'privacy_accepted_at')) {
                $table->timestamp('privacy_accepted_at')->nullable();
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'carrier_profile_type')) {
                $table->string('carrier_profile_type')->default('individual')->index();
            }

            if (! Schema::hasColumn('companies', 'allows_carrier_members')) {
                $table->boolean('allows_carrier_members')->default(false);
            }
        });

        Schema::create('carrier_company_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('carrier_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('driver');
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'carrier_id']);
        });

        Schema::table('loads', function (Blueprint $table) {
            if (! Schema::hasColumn('loads', 'cargo_photo_path')) {
                $table->string('cargo_photo_path')->nullable();
            }

            if (! Schema::hasColumn('loads', 'delivery_confirmation_token')) {
                $table->string('delivery_confirmation_token', 64)->nullable()->unique();
            }

            if (! Schema::hasColumn('loads', 'delivery_confirmation_code')) {
                $table->string('delivery_confirmation_code', 16)->nullable();
            }

            if (! Schema::hasColumn('loads', 'completion_confirmed_at')) {
                $table->timestamp('completion_confirmed_at')->nullable();
            }

            if (! Schema::hasColumn('loads', 'completion_confirmed_by')) {
                $table->foreignId('completion_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'photo_path')) {
                $table->string('photo_path')->nullable();
            }
        });

        Schema::table('bids', function (Blueprint $table) {
            if (! Schema::hasColumn('bids', 'contract_accepted_at')) {
                $table->timestamp('contract_accepted_at')->nullable();
            }

            if (! Schema::hasColumn('bids', 'contract_signed_at')) {
                $table->timestamp('contract_signed_at')->nullable();
            }

            if (! Schema::hasColumn('bids', 'contract_terms_version')) {
                $table->string('contract_terms_version')->default('2026-06');
            }

            if (! Schema::hasColumn('bids', 'carrier_cargo_photo_path')) {
                $table->string('carrier_cargo_photo_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            foreach (['contract_accepted_at', 'contract_signed_at', 'contract_terms_version', 'carrier_cargo_photo_path'] as $column) {
                if (Schema::hasColumn('bids', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'photo_path')) {
                $table->dropColumn('photo_path');
            }
        });

        Schema::table('loads', function (Blueprint $table) {
            if (Schema::hasColumn('loads', 'completion_confirmed_by')) {
                $table->dropConstrainedForeignId('completion_confirmed_by');
            }

            foreach (['cargo_photo_path', 'delivery_confirmation_token', 'delivery_confirmation_code', 'completion_confirmed_at'] as $column) {
                if (Schema::hasColumn('loads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('carrier_company_members');

        Schema::table('companies', function (Blueprint $table) {
            foreach (['carrier_profile_type', 'allows_carrier_members'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['terms_accepted_at', 'privacy_accepted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
