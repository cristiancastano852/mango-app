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
        $smlv = (float) config('payroll.smlv_monthly');
        $divisor = (int) config('payroll.hourly_divisor');
        $defaultHourly = round($smlv / max($divisor, 1), 2);

        Schema::table('surcharge_rules', function (Blueprint $table) use ($smlv, $defaultHourly) {
            $table->decimal('default_monthly_salary', 10, 2)->default($smlv)->after('night_end_time');
            $table->decimal('default_hourly_rate', 10, 2)->default($defaultHourly)->after('default_monthly_salary');
        });

        DB::table('surcharge_rules')->update([
            'default_monthly_salary' => $smlv,
            'default_hourly_rate' => $defaultHourly,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn(['default_monthly_salary', 'default_hourly_rate']);
        });
    }
};
