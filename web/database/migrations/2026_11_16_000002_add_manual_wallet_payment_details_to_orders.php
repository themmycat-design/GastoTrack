<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_sender_name', 160)->nullable()->after('payment_reference');
            $table->date('payment_received_on')->nullable()->after('payment_sender_name');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_sender_name', 'payment_received_on']);
        });
    }
};
