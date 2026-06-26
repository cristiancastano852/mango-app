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
            $table->boolean('pay_night_dominical')->default(true)->after('pay_dominical_by_default');
            $table->boolean('pay_night_holiday')->default(true)->after('pay_night_dominical');
            $table->boolean('pay_overtime_dominical')->default(true)->after('pay_night_holiday');
            $table->boolean('pay_overtime_holiday')->default(true)->after('pay_overtime_dominical');
        });

        // Preserva el comportamiento previo: una empresa que NO pagaba dominicales
        // (pay_dominical_by_default = false) tampoco pagaba la noche ni la extra dominical,
        // así que sus nuevos flags dominicales nacen apagados. Los festivos siempre se
        // pagaron, por lo que sus flags quedan en true (default de la columna).
        DB::table('surcharge_rules')->update([
            'pay_night_dominical' => DB::raw('pay_dominical_by_default'),
            'pay_overtime_dominical' => DB::raw('pay_dominical_by_default'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn([
                'pay_night_dominical',
                'pay_night_holiday',
                'pay_overtime_dominical',
                'pay_overtime_holiday',
            ]);
        });
    }
};
