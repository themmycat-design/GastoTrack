<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_reset_password')->default(false);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('plan_tier', 32)->default('free');
            $table->string('subscription_status', 32)->default('active');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->index(['plan_tier', 'subscription_status']);
        });

        Schema::create('admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'id']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex(['plan_tier', 'subscription_status']);
            $table->dropColumn(['plan_tier', 'subscription_status', 'subscription_ends_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_reset_password'));
    }
};
