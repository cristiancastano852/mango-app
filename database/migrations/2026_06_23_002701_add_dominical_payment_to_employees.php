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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('dominical_payment_mode')->default('hour')->after('receives_transport_allowance');
            $table->decimal('dominical_day_value', 12, 2)->default(0)->after('dominical_payment_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['dominical_payment_mode', 'dominical_day_value']);
        });
    }
};
