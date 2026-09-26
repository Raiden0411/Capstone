<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_application_id')
                ->constrained('business_applications')
                ->cascadeOnDelete();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('document_type', 40);

            // Marks a document row as a RENEWAL upload (vs. the initial
            // KYB submission). Renewal rows gate the tenant upload UI:
            // while an `is_renewal = true` row for a given document_type
            // is in verification_status = 'pending', the tenant cannot
            // re-upload that type.
            $table->boolean('is_renewal')->default(false);

            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('watermarked_path')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->nullable();

            $table->string('document_number')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->string('verification_status', 20)->default('pending');
            $table->text('verification_notes')->nullable();
            $table->timestamp('watermarked_at')->nullable();

            // Who reviewed this renewal and when. Null for the initial
            // KYB rows (those are reviewed as part of the parent
            // application, not per-document).
            $table->foreignId('renewal_reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('renewal_reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Short explicit index names (64-char limit)
            $table->index(['business_application_id', 'document_type'], 'bd_app_type_idx');
            $table->index('expires_at', 'bd_expires_at_idx');

            // Fast lookup: "does this tenant have a pending renewal
            // for this document type?" — the tenant upload guard.
            $table->index(
                ['business_application_id', 'document_type', 'is_renewal', 'verification_status'],
                'bd_renewal_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_documents');
    }
};