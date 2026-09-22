<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slow_query_aggregates', function (Blueprint $table) {
            $table->id();

            // sha256 hex digest of the raw SQL. 64 chars fits sha256.
            // UNIQUE because this is the upsert key.
            $table->string('query_hash', 64)->unique();

            // Example SQL text. Truncated on write to 2000 chars to
            // prevent a single absurd query from bloating the table.
            $table->text('sql_sample');

            // JSON-encoded array of example bindings. Nullable — the
            // harvest command may choose to omit if empty.
            $table->text('bindings_sample')->nullable();

            // Route name that triggered the FIRST slow occurrence.
            // Nullable because queries can run outside a routed request
            // (scheduled commands, tinker, queue workers).
            $table->string('route_name')->nullable();

            // Cumulative execution counters (incremented on every harvest).
            $table->unsignedBigInteger('count')->default(0);

            // Aggregated timings in milliseconds.
            $table->decimal('avg_ms',   10, 2)->default(0);
            $table->decimal('max_ms',   10, 2)->default(0);
            $table->decimal('total_ms', 14, 2)->default(0);

            // Window of observation for this aggregate.
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->timestamps();

            // Dashboard sort modes.
            $table->index('avg_ms',       'sqa_avg_idx');
            $table->index('total_ms',     'sqa_total_idx');
            $table->index('count',        'sqa_count_idx');
            $table->index('last_seen_at', 'sqa_recency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_query_aggregates');
    }
};