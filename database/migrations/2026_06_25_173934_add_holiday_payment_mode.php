<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modo de pago festivo (hour|day), igual que el dominical: default de empresa que siembra el
     * valor propio del empleado. Reusa el valor del día normal y el % sunday_holiday.
     */
    public function up(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->string('default_holiday_payment_mode')->default('hour')->after('default_normal_day_value');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('holiday_payment_mode')->default('hour')->after('normal_day_value');
        });
    }

    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn('default_holiday_payment_mode');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('holiday_payment_mode');
        });
    }
};
