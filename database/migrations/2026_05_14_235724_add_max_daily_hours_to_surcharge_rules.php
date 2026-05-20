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
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->integer('max_daily_hours')->default(8)->after('max_weekly_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn('max_daily_hours');
        });
    }
};
