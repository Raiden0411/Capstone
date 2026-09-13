<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_document_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('business_application_id')
                ->constrained('business_applications')
                ->cascadeOnDelete();

            $table->string('verification_type', 30);
            $table->string('source_field');
            $table->string('reference_value')->nullable();
            $table->string('submitted_value')->nullable();
            $table->float('confidence_score')->nullable();
            $table->boolean('matched')->default(false);

            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['business_application_id', 'verification_type'], 'bdv_app_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_document_verifications');
    }
};