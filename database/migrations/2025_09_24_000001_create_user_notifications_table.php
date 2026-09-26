<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Actor surface. Rule 165 — every read filters by (user_id, scope).
            $table->string('scope', 20)->default('platform');

            $table->string('type', 40)->default('system');
            $table->string('title');
            $table->text('message');
            $table->string('url', 500)->nullable();
            $table->string('icon', 40)->default('inbox');
            $table->string('color', 20)->default('slate');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope', 'read_at'],    'un_scoped_read_idx');
            $table->index(['user_id', 'scope', 'created_at'], 'un_scoped_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};