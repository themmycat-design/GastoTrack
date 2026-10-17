<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('violation_reports', 'bug_reports');

        Schema::table('bug_reports', function (Blueprint $table): void {
            $table->string('area', 48)->nullable()->after('category');
            $table->text('steps_to_reproduce')->nullable()->after('description');
            $table->string('screenshot_path')->nullable()->after('steps_to_reproduce');
            $table->text('public_update')->nullable()->after('resolution_notes');
            $table->boolean('is_legacy')->default(false)->after('public_update');
            $table->index(['business_id', 'created_at']);
            $table->index(['reported_by', 'created_at']);
            $table->index(['is_legacy', 'status']);
        });

        DB::table('bug_reports')->update(['is_legacy' => true]);
    }

    public function down(): void
    {
        Schema::table('bug_reports', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropIndex(['reported_by', 'created_at']);
            $table->dropIndex(['is_legacy', 'status']);
            $table->dropColumn([
                'area',
                'steps_to_reproduce',
                'screenshot_path',
                'public_update',
                'is_legacy',
            ]);
        });

        Schema::rename('bug_reports', 'violation_reports');
    }
};
