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
            $table->decimal('transport_allowance', 12, 2)->default(0)->after('default_hourly_rate');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('receives_transport_allowance')->default(true)->after('salary_type');
        });

        DB::table('surcharge_rules')->update([
            'transport_allowance' => (float) config('payroll.transport_allowance_monthly'),
        ]);

        DB::table('employees')->where('salary_type', 'monthly')->update([
            'receives_transport_allowance' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surcharge_rules', function (Blueprint $table) {
            $table->dropColumn('transport_allowance');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('receives_transport_allowance');
        });
    }
};
