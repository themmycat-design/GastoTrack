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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category'); // Milk Tea, Coffee, Snacks, etc.
            $table->decimal('price', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('prep_time')->nullable()->comment('Preparation time in minutes');
            $table->timestamps();
            
            $table->index(['business_id', 'category']);
            $table->index(['business_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
