<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantities already use four places. Preserve sub-cent ingredient costs
        // until the final product/waste total is rounded to currency precision.
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 4)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost_at_movement', 12, 4)->nullable()->change();
            $table->text('reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A rollback must not silently round costs or truncate recorded notes.
        if (DB::table('stock_items')->whereRaw('unit_cost <> ROUND(unit_cost, 2)')->exists()
            || DB::table('stock_movements')->whereRaw('unit_cost_at_movement <> ROUND(unit_cost_at_movement, 2)')->exists()
            || DB::table('stock_movements')->whereRaw('LENGTH(reason) > 255')->exists()) {
            throw new RuntimeException('Stock costs or notes exceed the old column limits; export/reconcile them before rolling back.');
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost_at_movement', 10, 2)->nullable()->change();
            $table->string('reason')->nullable()->change();
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 10, 2)->default(0)->change();
        });
    }
};
