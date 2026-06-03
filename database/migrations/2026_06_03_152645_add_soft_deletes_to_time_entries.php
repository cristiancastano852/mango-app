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
            $table->softDeletes();
            // Crear el índice nuevo primero: comparte el prefijo izquierdo employee_id,
            // de modo que la FK pueda apoyarse en él antes de soltar el índice viejo.
            $table->unique(['employee_id', 'date', 'deleted_at']);
            $table->dropUnique(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unique(['employee_id', 'date']);
            $table->dropUnique(['employee_id', 'date', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
