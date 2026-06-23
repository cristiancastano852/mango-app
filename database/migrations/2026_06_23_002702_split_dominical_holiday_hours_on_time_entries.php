<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separa las horas dominicales de las festivas. Los 4 buckets premium fusionados se
     * renombran a la familia `*_dominical` (conservan el valor histórico domingo+festivo) y
     * se agregan 4 columnas `*_holiday` en cero. Sin recálculo histórico: la separación
     * aplica hacia adelante (turnos nuevos/editados).
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->renameColumn('sunday_holiday_hours', 'dominical_hours');
            $table->renameColumn('night_sunday_hours', 'night_dominical_hours');
            $table->renameColumn('overtime_day_sunday_hours', 'overtime_day_dominical_hours');
            $table->renameColumn('overtime_night_sunday_hours', 'overtime_night_dominical_hours');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->decimal('holiday_hours', 5, 2)->default(0.00)->after('night_dominical_hours');
            $table->decimal('night_holiday_hours', 5, 2)->default(0.00)->after('holiday_hours');
            $table->decimal('overtime_day_holiday_hours', 5, 2)->default(0.00)->after('night_holiday_hours');
            $table->decimal('overtime_night_holiday_hours', 5, 2)->default(0.00)->after('overtime_day_holiday_hours');
        });
    }

    /**
     * Re-fusiona las horas festivas dentro de la familia dominical antes de dropear las
     * columnas `*_holiday`, restaurando el estado fusionado previo; luego revierte los nombres.
     */
    public function down(): void
    {
        DB::statement('UPDATE time_entries SET
            dominical_hours = dominical_hours + holiday_hours,
            night_dominical_hours = night_dominical_hours + night_holiday_hours,
            overtime_day_dominical_hours = overtime_day_dominical_hours + overtime_day_holiday_hours,
            overtime_night_dominical_hours = overtime_night_dominical_hours + overtime_night_holiday_hours');

        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn([
                'holiday_hours',
                'night_holiday_hours',
                'overtime_day_holiday_hours',
                'overtime_night_holiday_hours',
            ]);
        });

        Schema::table('time_entries', function (Blueprint $table) {
            $table->renameColumn('dominical_hours', 'sunday_holiday_hours');
            $table->renameColumn('night_dominical_hours', 'night_sunday_hours');
            $table->renameColumn('overtime_day_dominical_hours', 'overtime_day_sunday_hours');
            $table->renameColumn('overtime_night_dominical_hours', 'overtime_night_sunday_hours');
        });
    }
};
