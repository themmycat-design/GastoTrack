<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->foreignId('used_order_id')->nullable()->unique()->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('used_order_id');
        });
    }
};
