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
            $table->enum('payment_method', ['cash', 'gcash', 'maya', 'maribank', 'gotyme', 'bank'])
                ->nullable()->change();
        });
    }

    public function down(): void
    {
        // Do not erase the bank identity of existing sales during rollback.
        if (DB::table('orders')->whereIn('payment_method', ['maribank', 'gotyme'])->exists()) {
            throw new RuntimeException('Cannot roll back bank payment methods while MariBank or GoTyme orders exist.');
        }
        Schema::table('orders', function (Blueprint $table): void {
            $table->enum('payment_method', ['cash', 'gcash', 'maya', 'bank'])
                ->nullable()->change();
        });
    }
};
