<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_applications', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('coordinates');
            $table->string('owner_avatar_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('business_applications', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'owner_avatar_path']);
        });
    }
};