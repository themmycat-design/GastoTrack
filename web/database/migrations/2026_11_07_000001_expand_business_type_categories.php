<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing business types while allowing the three supported
        // shop categories to be stored as distinct values.
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('business_type', 50)->default('restaurant')->change();
        });
    }

    public function down(): void
    {
        // Map the new values back to the closest original enum value before
        // restoring the original column definition.
        DB::table('businesses')->where('business_type', 'milk_tea')->update(['business_type' => 'cafe']);
        DB::table('businesses')->where('business_type', 'coffee')->update(['business_type' => 'cafe']);
        DB::table('businesses')->where('business_type', 'bake_shop')->update(['business_type' => 'restaurant']);

        Schema::table('businesses', function (Blueprint $table): void {
            $table->enum('business_type', ['restaurant', 'cafe', 'retail', 'service', 'other'])
                ->default('restaurant')
                ->change();
        });
    }
};
