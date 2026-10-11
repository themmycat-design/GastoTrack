<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status', 24)->default('pending')->after('status');
            $table->char('payment_currency', 3)->default('PHP');
            $table->decimal('detected_payment_amount', 10, 2)->nullable();
            $table->char('detected_payment_currency', 3)->nullable();
            $table->timestamp('payment_detected_at')->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->foreignId('payment_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->timestamp('financials_committed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refund_requested_at')->nullable();
            $table->foreignId('refund_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refund_reason')->nullable();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->string('refund_reference', 120)->nullable();
            $table->foreignId('refund_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refund_confirmed_at')->nullable();
            $table->boolean('refund_restocked')->default(false);
            $table->char('idempotency_hash', 64)->nullable();
            $table->unique(
                ['business_id', 'payment_method', 'payment_reference'],
                'orders_business_payment_reference_unique'
            );
            $table->unique(['business_id', 'refund_reference'], 'orders_business_refund_reference_unique');
            $table->index(['business_id', 'payment_status']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete()->unique();
            $table->foreignId('reversal_of_id')->nullable()->constrained('transactions')->restrictOnDelete()->unique();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('ingredients_snapshot')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements')->restrictOnDelete()->unique();
        });

        Schema::create('order_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('from_payment_status', 24)->nullable();
            $table->string('to_payment_status', 24)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('reference', 120)->nullable();
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
            $table->index(['business_id', 'event_type']);
        });

        // Previous releases completed every cashless order from a matching device
        // notification. That signal was not provider verification. Keep the old
        // accounting entries intact, but require staff review before treating
        // those legacy orders as verified.
        DB::table('orders')
            ->where('status', 'completed')
            ->whereIn('payment_method', ['gcash', 'maya', 'bank'])
            ->update([
                'payment_status' => 'detected',
                'financials_committed_at' => DB::raw('COALESCE(completed_at, created_at)'),
            ]);

        DB::table('orders')
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->update([
                'payment_status' => 'confirmed',
                'payment_confirmed_at' => DB::raw('COALESCE(completed_at, created_at)'),
                'financials_committed_at' => DB::raw('COALESCE(completed_at, created_at)'),
            ]);

        DB::table('orders')
            ->where('status', 'cancelled')
            ->update(['payment_status' => 'cancelled']);

        DB::table('orders')
            ->where('status', 'completed')
            ->whereIn('payment_method', ['gcash', 'maya', 'bank'])
            ->orderBy('id')
            ->chunkById(250, function ($orders): void {
                $now = now();
                DB::table('order_payment_events')->insert($orders->map(fn ($order): array => [
                    'order_id' => $order->id,
                    'business_id' => $order->business_id,
                    'user_id' => null,
                    'event_type' => 'legacy_payment_review_required',
                    'from_status' => 'completed',
                    'to_status' => 'completed',
                    'from_payment_status' => null,
                    'to_payment_status' => 'detected',
                    'amount' => $order->total,
                    'currency' => 'PHP',
                    'reference' => null,
                    'reason' => 'Prior version treated a device notification as payment confirmation. Staff must verify the provider record manually.',
                    'details' => json_encode(['legacy_financials_preserved' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_events');

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropColumn('reversal_of_id');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['reversal_of_id']);
            $table->dropUnique(['reversal_of_id']);
            $table->dropForeign(['order_id']);
            $table->dropUnique(['order_id']);
            $table->dropColumn(['order_id', 'reversal_of_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('ingredients_snapshot');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['payment_confirmed_by']);
            $table->dropForeign(['refund_requested_by']);
            $table->dropForeign(['refund_confirmed_by']);
            $table->dropUnique('orders_business_payment_reference_unique');
            $table->dropUnique('orders_business_refund_reference_unique');
            $table->dropIndex(['business_id', 'payment_status']);
            $table->dropColumn([
                'payment_status', 'payment_currency', 'detected_payment_amount',
                'detected_payment_currency', 'payment_detected_at', 'payment_reference',
                'payment_confirmed_by', 'payment_confirmed_at', 'financials_committed_at',
                'cancelled_at', 'refund_requested_at', 'refund_requested_by', 'refund_reason',
                'refund_amount', 'refund_reference', 'refund_confirmed_by', 'refund_confirmed_at',
                'refund_restocked', 'idempotency_hash',
            ]);
        });
    }
};
