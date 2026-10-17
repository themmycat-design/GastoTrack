<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('product_cost_snapshot', 10, 2)->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('movement_category', 24)->default('adjustment');
            $table->decimal('unit_cost_at_movement', 10, 2)->nullable();
            $table->index(['movement_category', 'created_at'], 'stock_movements_category_created_index');
        });

        // Preserve the known meaning of historical order deductions and refund
        // restorations. Other old movements remain ordinary adjustments because
        // their free-text reason cannot safely establish that they were waste.
        \Illuminate\Support\Facades\DB::table('stock_movements')
            ->whereNotNull('order_id')
            ->where('type', 'out')
            ->update(['movement_category' => 'sale']);

        \Illuminate\Support\Facades\DB::table('stock_movements')
            ->whereNotNull('order_id')
            ->where('type', 'in')
            ->whereNotNull('reversal_of_id')
            ->update(['movement_category' => 'return']);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex('stock_movements_category_created_index');
            $table->dropColumn(['movement_category', 'unit_cost_at_movement']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('product_cost_snapshot');
        });
    }
};
