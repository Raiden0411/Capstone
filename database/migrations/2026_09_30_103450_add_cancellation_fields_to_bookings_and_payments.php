<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Who initiated the cancellation. Null until cancelled.
            // 'tourist' | 'admin'
            $table->string('cancelled_by', 20)->nullable()->after('booking_type');

            // Free-form reason captured from the modal, or an
            // admin-supplied note on business-initiated cancels.
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');

            // Computed at cancel time. Amount is in PHP peso, rounded
            // to 2dp. Percentage is one of 0, 50, 100.
            $table->decimal('refund_amount', 10, 2)->nullable()->after('cancellation_reason');
            $table->unsignedTinyInteger('refund_percentage')->nullable()->after('refund_amount');

            // Lifecycle: none → pending → processed | rejected
            // 'none' means no refund is owed (0% tier, or booking was
            // unpaid). Rows in 'none' never enter the async pipeline.
            $table->string('refund_status', 20)->default('none')->after('refund_percentage');

            $table->timestamp('cancelled_at')->nullable()->after('refund_status');
            $table->timestamp('refund_processed_at')->nullable()->after('cancelled_at');

            // PayMongo refund object ID (re_xxx). Null until the async
            // job calls the Refunds API successfully.
            $table->string('paymongo_refund_id', 100)->nullable()->after('refund_processed_at');

            $table->index(['refund_status', 'cancelled_at'], 'bookings_refund_status_cancelled_at_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            // The PayMongo payment object ID (pay_xxx), which the
            // Refunds API requires. Distinct from paymongo_session_id
            // (cs_xxx) — a session may contain multiple payment
            // attempts; only one succeeds, and that one's ID is what
            // we refund against.
            $table->string('paymongo_payment_id', 100)->nullable()->after('paymongo_session_id');

            $table->index('paymongo_payment_id', 'payments_paymongo_payment_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_refund_status_cancelled_at_idx');
            $table->dropColumn([
                'cancelled_by',
                'cancellation_reason',
                'refund_amount',
                'refund_percentage',
                'refund_status',
                'cancelled_at',
                'refund_processed_at',
                'paymongo_refund_id',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paymongo_payment_idx');
            $table->dropColumn('paymongo_payment_id');
        });
    }
};