<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('transaction_type', 20)->default('all');
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['business_id', 'kind', 'transaction_type', 'name'], 'transaction_options_unique');
            $table->index(['business_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_options');
    }
};
