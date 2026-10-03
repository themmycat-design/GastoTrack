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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Modify the role enum to include super_admin
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'staff', 'super_admin') DEFAULT 'staff'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        // Revert back to original enum
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'staff') DEFAULT 'staff'");
    }
};
