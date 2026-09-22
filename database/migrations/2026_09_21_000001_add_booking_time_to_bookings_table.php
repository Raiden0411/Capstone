<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The check_in / check_out columns are DATE columns — they cannot
     * carry a time component. The booking flow captures a single
     * start-time ("Used for both start and end") that applies to the
     * whole reservation. Store it here so the receipt and any future
     * scheduler can read it.
     *
     * Format: 'HH:MM' — 24-hour, zero-padded, no seconds.
     * Example: '04:00', '14:30'.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_time', 5)
                ->nullable()
                ->after('check_out');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('booking_time');
        });
    }
};