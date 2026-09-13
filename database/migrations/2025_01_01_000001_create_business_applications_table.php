<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // ── Draft-allowed: null until the applicant fills them in ──
            $table->string('business_name')->nullable();
            $table->string('business_type', 20)->nullable();
            $table->string('business_registration_number')->nullable();

            // ── Canonical form of the registration number ──
            // Populated by BusinessApplication::booted().
            // "cs2024 1234567" → "CS20241234567"
            $table->string('business_registration_number_canonical', 100)->nullable();

            $table->string('tin_number', 20)->nullable();

            // ── Canonical form of the TIN ──
            // Populated by BusinessApplication::booted().
            // "123-456-789-000" → "123456789000"
            $table->string('tin_canonical', 30)->nullable();

            $table->string('owner_full_name')->nullable();
            $table->string('owner_id_type', 50)->nullable();
            $table->string('owner_id_number')->nullable();
            $table->date('owner_birthdate')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();

            // ── Location ──
            $table->string('address')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->json('coordinates')->nullable();

            $table->foreignId('type_of_tenant_id')
                ->nullable()
                ->constrained('type_of_tenants')
                ->nullOnDelete();

            // ── Review workflow ──
            $table->string('status', 30)->default('draft');

            $table->text('rejection_reason')->nullable();
            $table->text('revision_notes')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Short explicit index names (64-char limit)
            $table->index(['user_id', 'status'], 'ba_user_status_idx');
            $table->index('status', 'ba_status_idx');
            $table->index('tin_number', 'ba_tin_idx');
            $table->index('tin_canonical', 'ba_tin_canonical_idx');
            $table->index('business_registration_number_canonical', 'ba_reg_canonical_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_applications');
    }
};