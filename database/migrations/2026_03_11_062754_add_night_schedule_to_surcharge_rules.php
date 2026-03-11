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
            $table->time('night_start_time')->default('21:00');
            $table->time('night_end_time')->default('06:00');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn(['night_start_time', 'night_end_time']);
        });
    }
};
