<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->decimal('pack_price', 12, 2)->nullable()->after('unit_cost');
            $table->decimal('pack_quantity', 12, 4)->nullable()->after('pack_price');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', fn (Blueprint $table) => $table->dropColumn(['pack_price', 'pack_quantity']));
    }
};
