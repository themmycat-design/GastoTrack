<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep eight integer digits while retaining four fractional digits.
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->decimal('current_quantity', 12, 4)->default(0)->change();
            $table->decimal('minimum_quantity', 12, 4)->default(0)->change();
        });
        Schema::table('product_ingredients', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 4)->change();
        });
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 4)->change();
            $table->decimal('previous_quantity', 12, 4)->change();
            $table->decimal('new_quantity', 12, 4)->change();
        });
    }

    public function down(): void
    {
        // Narrowing these columns would silently round stock and movement history.
        // Leave the wider representation in place when rolling back this migration.
    }
};
