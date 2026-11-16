<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_unit_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 20);
            $table->timestamps();
            $table->unique(['business_id', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_unit_options');
    }
};
