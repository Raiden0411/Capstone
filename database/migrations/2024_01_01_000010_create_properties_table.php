<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete(); // Ties it to the tenant/owner
            
            // The missing relationship to your property_types table!
            $table->foreignId('property_type_id')->constrained('property_types')->cascadeOnDelete();
            
            $table->string('name'); // e.g., "Cottage 1", "Deluxe Room"
            $table->text('description')->nullable();
            
            // Swapped order of price/capacity to match your Livewire component flow, but order doesn't strictly matter
            $table->integer('capacity')->default(1); // How many people it holds
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('status')->default('available'); // available, occupied, maintenance
            
            // Added this because your Livewire component has a checkbox for it!
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};