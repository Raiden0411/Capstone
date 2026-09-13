<?php
// database/migrations/2025_01_01_000005_add_kyb_columns_to_tenants_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->date('permit_expires_at')->nullable()->after('is_recommended');
            $table->timestamp('verified_at')->nullable()->after('permit_expires_at');
            $table->timestamp('last_permit_prompt_at')->nullable()->after('verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('active_mode', 20)->default('tourist')->after('is_active');
            // tourist | business — used for mode switching in the header
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['permit_expires_at', 'verified_at', 'last_permit_prompt_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_mode');
        });
    }
};