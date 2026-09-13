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

            $table->timestamps();
            $table->softDeletes();

            // Short explicit index names
            $table->index(['business_application_id', 'document_type'], 'bd_app_type_idx');
            $table->index('expires_at', 'bd_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_documents');
    }
};