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
        'net_hours',
        'regular_hours',
        'night_hours',
        'sunday_holiday_hours',
        'night_sunday_hours',
        'overtime_day_hours',
        'overtime_night_hours',
        'overtime_day_sunday_hours',
        'overtime_night_sunday_hours',
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
            'net_hours' => 'decimal:2',
            // Semana + diurno + dentro de límite
            'regular_hours' => 'decimal:2',
            // Semana + nocturno (21:00–06:00) + dentro de límite
            'night_hours' => 'decimal:2',
            // Dom/festivo + diurno + dentro de límite
            'sunday_holiday_hours' => 'decimal:2',
            // Dom/festivo + nocturno + dentro de límite
            'night_sunday_hours' => 'decimal:2',
            // Semana + diurno + supera límite diario o semanal
            'overtime_day_hours' => 'decimal:2',
            // Semana + nocturno + supera límite diario o semanal
            'overtime_night_hours' => 'decimal:2',
            // Dom/festivo + diurno + supera límite diario o semanal
            'overtime_day_sunday_hours' => 'decimal:2',
            // Dom/festivo + nocturno + supera límite diario o semanal
            'overtime_night_sunday_hours' => 'decimal:2',
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
}
