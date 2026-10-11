<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_notification_captures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_id')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16);
            $table->string('direction', 16)->default('unknown');
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->string('merchant', 160)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['business_id', 'captured_at']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_notification_captures');
    }
};
