<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El valor por día deja de ser "el recargo plano" y pasa a ser el "valor del día normal":
     * el recargo dominical se calcula aplicando el % configurable (sunday_holiday) sobre ese valor.
     */
    public function up(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->renameColumn('default_dominical_day_value', 'default_normal_day_value');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('dominical_day_value', 'normal_day_value');
        });
    }

    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->renameColumn('default_normal_day_value', 'default_dominical_day_value');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->renameColumn('normal_day_value', 'dominical_day_value');
        });
    }
};
