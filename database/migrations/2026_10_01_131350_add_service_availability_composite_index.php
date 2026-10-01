<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_availability', function (Blueprint $table) {
            $table->index(
                ['tenant_id', 'service_id', 'date'],
                'sa_tenant_svc_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('service_availability', function (Blueprint $table) {
            $table->dropIndex('sa_tenant_svc_date_idx');
        });
    }
};