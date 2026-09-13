<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_renewal_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->date('permit_expires_at')->nullable();

            $table->string('status', 20)->default('pending');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('renewed_document_id')
                ->nullable()
                ->constrained('business_documents')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'year'], 'prr_tenant_year_unique');
            $table->index('status', 'prr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_renewal_reminders');
    }
};