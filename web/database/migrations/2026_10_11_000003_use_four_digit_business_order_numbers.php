<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Count soft-deleted orders too: a cancelled/deleted number is never reused.
        $tooMany = DB::table('orders')
            ->select('business_id')
            ->groupBy('business_id')
            ->havingRaw('COUNT(*) > 9999')
            ->first();
        if ($tooMany) {
            throw new RuntimeException('Business '.$tooMany->business_id.' has more than 9999 historical orders; four-digit numbering cannot be applied safely.');
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedInteger('next_order_number')->default(1);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('legacy_order_number')->nullable();
            $table->dropUnique('orders_order_number_unique');
        });

        // Preserve the original reference for audit and historical search.
        DB::table('orders')->update(['legacy_order_number' => DB::raw('order_number')]);

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $next = 1;
            $orders = DB::table('orders')
                ->where('business_id', $businessId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');
            foreach ($orders as $orderId) {
                DB::table('orders')->where('id', $orderId)
                    ->update(['order_number' => str_pad((string) $next++, 4, '0', STR_PAD_LEFT)]);
            }
            DB::table('businesses')->where('id', $businessId)
                ->update(['next_order_number' => $next]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['business_id', 'order_number'], 'orders_business_order_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_business_order_number_unique');
        });

        $reserved = array_fill_keys(
            DB::table('orders')->whereNotNull('legacy_order_number')->pluck('legacy_order_number')->all(),
            true
        );
        foreach (DB::table('orders')->orderBy('id')->get(['id', 'legacy_order_number']) as $order) {
            $restored = $order->legacy_order_number;
            if ($restored === null) {
                $restored = 'ROLLBACK-NEW-ORDER-'.$order->id;
                while (isset($reserved[$restored])) {
                    $restored .= '-R';
                }
                $reserved[$restored] = true;
            }
            DB::table('orders')->where('id', $order->id)->update([
                'order_number' => $restored,
            ]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('order_number', 'orders_order_number_unique');
            $table->dropColumn('legacy_order_number');
        });
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('next_order_number');
        });
    }
};
