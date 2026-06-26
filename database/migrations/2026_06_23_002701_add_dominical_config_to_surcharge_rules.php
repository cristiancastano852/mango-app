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
            $table->unsignedTinyInteger('dominical_weekday')->default(0)->after('transport_allowance');
            $table->boolean('pay_dominical_by_default')->default(true)->after('dominical_weekday');
            $table->string('default_dominical_payment_mode')->default('hour')->after('pay_dominical_by_default');
            $table->decimal('default_dominical_day_value', 12, 2)->default(0)->after('default_dominical_payment_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn([
                'dominical_weekday',
                'pay_dominical_by_default',
                'default_dominical_payment_mode',
                'default_dominical_day_value',
            ]);
        });
    }
};
