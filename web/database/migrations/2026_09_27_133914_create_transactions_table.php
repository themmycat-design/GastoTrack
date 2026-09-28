<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['income', 'expense']);
            $table->string('category'); // Sales, Delivery, Ingredients, Rent, Salaries, etc.
            $table->decimal('amount', 10, 2);
            $table->text('description')->nullable();
            $table->string('source')->nullable(); // Cash, GCash, Maya, Bank, etc.
            $table->string('receipt_image')->nullable();
            $table->date('transaction_date');
            $table->timestamps();
            
            $table->index(['business_id', 'transaction_date']);
            $table->index(['business_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
