<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            // Existing goals remain manual; monthly sales goals never persist progress.
            $table->string('goal_type', 32)->default('manual');
            $table->date('period_start')->nullable();
            $table->unique(['business_id', 'goal_type', 'period_start'], 'goals_business_type_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropUnique('goals_business_type_month_unique');
            $table->dropColumn(['goal_type', 'period_start']);
        });
    }
};
