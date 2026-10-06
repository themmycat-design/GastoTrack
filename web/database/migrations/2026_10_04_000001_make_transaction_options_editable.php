<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_options', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('name');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_options', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('is_default');
        });
    }
};
