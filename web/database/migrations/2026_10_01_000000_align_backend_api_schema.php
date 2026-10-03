<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','preparing','ready','completed','cancelled') DEFAULT 'pending'");
        }
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('currency', 3)->default('PHP');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->string('entry_method')->default('manual');
            $table->json('metadata')->nullable();
            $table->uuid('client_id')->nullable()->unique();
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('emoji', 10)->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->softDeletes();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->unique();
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')->nullable()->unique();
            $table->decimal('discount', 10, 2)->default(0);
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->uuid('client_id')->nullable()->unique();
            $table->softDeletes();
        });

        Schema::table('goals', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->unique();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');

        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn('client_id'));
        Schema::table('goals', fn (Blueprint $table) => $table->dropColumn('completed_at'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['order_number', 'discount', 'cancelled_by', 'cancel_reason', 'prepared_at', 'ready_at', 'client_id', 'deleted_at']);
        });
        Schema::table('stock_items', fn (Blueprint $table) => $table->dropColumn(['client_id', 'deleted_at']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['cost', 'emoji', 'is_available', 'display_order', 'deleted_at']));
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn(['entry_method', 'metadata', 'client_id', 'deleted_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['status', 'deleted_at']));
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'description', 'location', 'city', 'province', 'currency']);
        });
    }
};
