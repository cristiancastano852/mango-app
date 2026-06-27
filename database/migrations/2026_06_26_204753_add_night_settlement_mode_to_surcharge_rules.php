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
            $table->string('night_settlement_mode')->default('immediate')->after('overtime_accrual_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn('night_settlement_mode');
        });
    }
};
