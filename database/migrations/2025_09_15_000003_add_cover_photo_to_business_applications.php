<?php
// database/migrations/2025_09_15_000003_add_cover_photo_to_business_applications.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_applications', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('business_applications', function (Blueprint $table) {
            $table->dropColumn('cover_photo_path');
        });
    }
};