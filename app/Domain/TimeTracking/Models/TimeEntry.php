<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use App\Models\User;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): TimeEntryFactory
    {
        return TimeEntryFactory::new();
    }

    protected $fillable = [
        'employee_id',
        'company_id',
        'date',
        'clock_in',
        'clock_out',
        'gross_hours',
        'break_hours',
        'paid_break_overage_hours',
        'net_hours',
        'regular_hours',
        'night_hours',
        'dominical_hours',
        'night_dominical_hours',
        'holiday_hours',
        'night_holiday_hours',
        'overtime_day_hours',
        'overtime_night_hours',
        'overtime_day_dominical_hours',
        'overtime_night_dominical_hours',
        'overtime_day_holiday_hours',
        'overtime_night_holiday_hours',
        'status',
        'edited_by',
        'edit_reason',
        'pin_verified',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'gross_hours' => 'decimal:2',
            'break_hours' => 'decimal:2',
            // Exceso de pausas pagadas descontado del tiempo trabajado (parte que supera max_duration_minutes)
            'paid_break_overage_hours' => 'decimal:2',
            'net_hours' => 'decimal:2',
            // Semana + diurno + dentro de límite
            'regular_hours' => 'decimal:2',
            // Semana + nocturno (21:00–06:00) + dentro de límite
            'night_hours' => 'decimal:2',
            // Dominical + diurno + dentro de límite
            'dominical_hours' => 'decimal:2',
            // Dominical + nocturno + dentro de límite
            'night_dominical_hours' => 'decimal:2',
            // Festivo + diurno + dentro de límite
            'holiday_hours' => 'decimal:2',
            // Festivo + nocturno + dentro de límite
            'night_holiday_hours' => 'decimal:2',
            // Semana + diurno + supera límite diario o semanal
            'overtime_day_hours' => 'decimal:2',
            // Semana + nocturno + supera límite diario o semanal
            'overtime_night_hours' => 'decimal:2',
            // Dominical + diurno + supera límite diario o semanal
            'overtime_day_dominical_hours' => 'decimal:2',
            // Dominical + nocturno + supera límite diario o semanal
            'overtime_night_dominical_hours' => 'decimal:2',
            // Festivo + diurno + supera límite diario o semanal
            'overtime_day_holiday_hours' => 'decimal:2',
            // Festivo + nocturno + supera límite diario o semanal
            'overtime_night_holiday_hours' => 'decimal:2',
            'pin_verified' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakEntry::class);
    }

    /**
     * Horas de pausas pagadas finalizadas (no descuentan del tiempo trabajado;
     * el complemento break_hours solo suma las no pagadas). Requiere breaks.breakType cargados.
     */
    public function paidBreakHours(): float
    {
        return round(
            $this->breaks
                ->filter(fn (BreakEntry $break) => $break->ended_at !== null && (bool) $break->breakType?->is_paid)
                ->sum(fn (BreakEntry $break) => max(0, (int) $break->duration_minutes)) / 60,
            2,
        );
    }

    /**
     * Horas de exceso de pausas pagadas que SÍ se descuentan del tiempo trabajado:
     * por cada pausa pagada finalizada con max_duration_minutes definido, la porción que
     * supera ese límite (max(0, duration - max_duration_minutes)). Las pausas pagadas sin
     * límite y las no pagadas no aportan. Consulta la relación para ser robusto sin eager loading.
     */
    public function paidBreakOverageHours(): float
    {
        return round(
            $this->breaks()
                ->whereNotNull('ended_at')
                ->whereHas('breakType', fn ($query) => $query->where('is_paid', true)->whereNotNull('max_duration_minutes'))
                ->with('breakType')
                ->get()
                ->sum(fn (BreakEntry $break) => max(0, (int) $break->duration_minutes - (int) $break->breakType->max_duration_minutes)) / 60,
            2,
        );
    }
}
