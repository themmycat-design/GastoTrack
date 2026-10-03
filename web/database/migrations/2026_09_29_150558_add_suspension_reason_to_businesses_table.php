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
        Schema::table('businesses', function (Blueprint $table) {
            // Add status column
            $table->enum('status', ['pending', 'active', 'suspended', 'inactive'])->default('pending')->after('active');
            // Add suspension reason
            $table->text('suspension_reason')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['status', 'suspension_reason']);
        });
    }
};
