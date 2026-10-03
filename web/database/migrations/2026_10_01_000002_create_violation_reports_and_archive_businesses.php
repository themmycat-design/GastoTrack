<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::create('violation_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->enum('category', ['complaint', 'policy_violation', 'fraud_risk', 'account_issue', 'other'])->default('complaint');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'under_review', 'resolved', 'dismissed'])->default('open');
            $table->text('description');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('violation_reports');

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
