<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tooMany = DB::table('orders')
            ->select('business_id')
            ->groupBy('business_id')
            ->havingRaw('COUNT(*) > 8999')
            ->first();

        if ($tooMany) {
            throw new RuntimeException('Business '.$tooMany->business_id.' has more than 8999 historical orders; numbering from 1001 to 9999 cannot be applied safely.');
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedInteger('next_order_number')->default(1001)->change();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_business_order_number_unique');
        });

        // Keep archived and cancelled order numbers in the same never-resetting sequence.
        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $next = 1001;
            $orderIds = DB::table('orders')
                ->where('business_id', $businessId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($orderIds as $orderId) {
                DB::table('orders')->where('id', $orderId)
                    ->update(['order_number' => (string) $next++]);
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

        foreach (DB::table('businesses')->orderBy('id')->pluck('id') as $businessId) {
            $next = 1;
            $orderIds = DB::table('orders')
                ->where('business_id', $businessId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->pluck('id');

            foreach ($orderIds as $orderId) {
                DB::table('orders')->where('id', $orderId)
                    ->update(['order_number' => str_pad((string) $next++, 4, '0', STR_PAD_LEFT)]);
            }

            DB::table('businesses')->where('id', $businessId)
                ->update(['next_order_number' => $next]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['business_id', 'order_number'], 'orders_business_order_number_unique');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedInteger('next_order_number')->default(1)->change();
        });
    }
};
