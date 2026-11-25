<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->string('capture_method', 24)->default('notification');
            $table->string('screenshot_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_notification_captures', function (Blueprint $table): void {
            $table->dropColumn(['capture_method', 'screenshot_path']);
        });
    }
};
