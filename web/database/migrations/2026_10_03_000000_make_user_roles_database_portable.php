<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL was already expanded by the 2026_09_29 migration. SQLite and
        // other test/dev databases may still have the original enum CHECK
        // constraint, which rejects the super_admin role.
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            return;
        }

        if ($driver === 'sqlite') {
            $definition = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'"
            )?->sql;
            $definition = strtolower((string) $definition);

            // Fresh databases already use the portable string column. Only
            // rebuild older SQLite tables that still contain an enum check.
            if (!str_contains($definition, 'check') || str_contains($definition, 'super_admin')) {
                return;
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('staff')->change();
        });
    }

    public function down(): void
    {
        // Do not restore the restrictive role constraint because that could
        // invalidate existing super administrator accounts.
    }
};
