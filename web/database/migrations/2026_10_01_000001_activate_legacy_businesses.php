<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Businesses created before the status workflow already used `active`
        // as their approval flag. Preserve that state instead of leaving them
        // incorrectly marked as pending after the status column was introduced.
        DB::table('businesses')
            ->where('active', true)
            ->where('status', 'pending')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        // This is a one-time data correction and should not be reversed.
    }
};
