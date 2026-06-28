<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('tax_system')->nullable()->after('ogrn');
            $table->string('director_name')->nullable()->after('actual_address');
            $table->string('contact_person')->nullable()->after('director_name');
            $table->string('bank_name')->nullable()->after('contact_person');
            $table->string('bank_bik')->nullable()->after('bank_name');
            $table->string('bank_account')->nullable()->after('bank_bik');
            $table->string('correspondent_account')->nullable()->after('bank_account');
            $table->text('verification_comment')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verification_comment');
            $table->timestamp('rejected_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'tax_system',
                'director_name',
                'contact_person',
                'bank_name',
                'bank_bik',
                'bank_account',
                'correspondent_account',
                'verification_comment',
                'verified_at',
                'rejected_at',
            ]);
        });
    }
};
