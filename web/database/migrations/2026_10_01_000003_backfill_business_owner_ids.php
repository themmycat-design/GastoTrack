<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('businesses')
            ->whereNull('owner_id')
            ->orderBy('id')
            ->eachById(function ($business) {
                $ownerId = DB::table('users')
                    ->where('business_id', $business->id)
                    ->where('role', 'owner')
                    ->whereNull('deleted_at')
                    ->oldest('id')
                    ->value('id');

                if ($ownerId) {
                    DB::table('businesses')
                        ->where('id', $business->id)
                        ->update(['owner_id' => $ownerId]);
                }
            });
    }

    public function down(): void
    {
        // The original owner links are valid data and should not be removed.
    }
};
