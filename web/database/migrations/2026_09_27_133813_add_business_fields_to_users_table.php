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
        Schema::table('users', function (Blueprint $table) {
            // Keep roles portable across MySQL and SQLite. Authorization and
            // request validation enforce the supported values in the app.
            $table->string('role', 32)->default('staff')->after('email');
            $table->foreignId('business_id')->nullable()->after('role')->constrained()->onDelete('cascade');
            $table->string('phone')->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropColumn(['role', 'business_id', 'phone']);
        });
    }
};
