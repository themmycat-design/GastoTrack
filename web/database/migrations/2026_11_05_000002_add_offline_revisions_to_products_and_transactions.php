<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(0));
        Schema::table('transactions', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(0));
        Schema::table('stock_items', fn (Blueprint $table) => $table->unsignedInteger('revision')->default(0));
    }

    public function down(): void
    {
        Schema::table('stock_items', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('revision'));
    }
};
