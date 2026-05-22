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
        Schema::table('time_entries', function (Blueprint $table) {
            $table->renameColumn('overtime_hours', 'overtime_day_hours');
            $table->decimal('overtime_night_hours', 5, 2)->default(0.00)->after('overtime_day_hours');
            $table->decimal('night_sunday_hours', 5, 2)->default(0.00)->after('overtime_night_hours');
            $table->decimal('overtime_day_sunday_hours', 5, 2)->default(0.00)->after('night_sunday_hours');
            $table->decimal('overtime_night_sunday_hours', 5, 2)->default(0.00)->after('overtime_day_sunday_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn(['overtime_night_hours', 'night_sunday_hours', 'overtime_day_sunday_hours', 'overtime_night_sunday_hours']);
            $table->renameColumn('overtime_day_hours', 'overtime_hours');
        });
    }
};
