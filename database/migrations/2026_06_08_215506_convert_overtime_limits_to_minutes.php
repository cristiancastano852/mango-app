<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->renameColumn('max_daily_hours', 'max_daily_minutes');
            $table->renameColumn('max_weekly_hours', 'max_weekly_minutes');
        });

        DB::table('surcharge_rules')->update([
            'max_daily_minutes' => DB::raw('max_daily_minutes * 60'),
            'max_weekly_minutes' => DB::raw('max_weekly_minutes * 60'),
        ]);

        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->integer('max_daily_minutes')->default(480)->change();
            $table->integer('max_weekly_minutes')->default(2520)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('surcharge_rules')->update([
            'max_daily_minutes' => DB::raw('max_daily_minutes / 60'),
            'max_weekly_minutes' => DB::raw('max_weekly_minutes / 60'),
        ]);

        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->renameColumn('max_daily_minutes', 'max_daily_hours');
            $table->renameColumn('max_weekly_minutes', 'max_weekly_hours');
        });

        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->integer('max_daily_hours')->default(8)->change();
            $table->integer('max_weekly_hours')->default(42)->change();
        });
    }
};
