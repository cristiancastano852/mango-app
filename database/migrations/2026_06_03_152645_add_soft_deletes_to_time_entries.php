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
        });

        // Columna generada: 1 cuando el registro está activo (deleted_at NULL), NULL cuando está eliminado.
        // Permite un índice único que garantiza UN solo registro activo por empleado/día a nivel de BD,
        // sin bloquear la coexistencia de registros eliminados (sus active_marker NULL son distintos entre sí).
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_marker')
                ->storedAs('case when deleted_at is null then 1 else null end')
                ->nullable();

            // Crear el índice nuevo primero: comparte el prefijo izquierdo employee_id,
            // de modo que la FK pueda apoyarse en él antes de soltar el índice viejo.
            $table->unique(['employee_id', 'date', 'active_marker']);
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
            $table->dropUnique(['employee_id', 'date', 'active_marker']);
            $table->dropColumn('active_marker');
            $table->dropSoftDeletes();
        });
    }
};
