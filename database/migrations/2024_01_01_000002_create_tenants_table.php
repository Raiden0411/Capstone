<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('type_of_tenant_id')->constrained('type_of_tenants')->cascadeOnDelete();
            $table->string('address');
            $table->string('contact_number');
            $table->string('email');
            $table->string('logo')->nullable();
            $table->json('coordinates')->nullable();   // replaces latitude/longitude
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};