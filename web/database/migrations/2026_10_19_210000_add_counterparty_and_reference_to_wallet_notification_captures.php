<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->string('counterparty_name', 160)->nullable();
            $table->string('reference', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->dropColumn(['counterparty_name', 'reference']);
        });
    }
};
