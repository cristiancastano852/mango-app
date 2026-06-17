<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use Database\Factories\BreakEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakEntry extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): BreakEntryFactory
    {
        return BreakEntryFactory::new();
    }

    protected $table = 'breaks';

    protected $fillable = [
        'time_entry_id',
        'employee_id',
        'company_id',
        'break_type_id',
        'started_at',
        'ended_at',
        'duration_minutes',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function timeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function breakType(): BelongsTo
    {
        return $this->belongsTo(BreakType::class);
    }

    /**
     * Shape único de una pausa para vistas y reportes (requiere breakType cargado).
     *
     * @return array{name: ?string, icon: ?string, color: ?string, is_paid: bool, started_at: ?string, ended_at: ?string, duration_minutes: ?int, overage_minutes: int, in_progress: bool}
     */
    public function toDisplayArray(): array
    {
        $inProgress = $this->ended_at === null;

        return [
            'name' => $this->breakType?->name,
            'icon' => $this->breakType?->icon,
            'color' => $this->breakType?->color,
            'is_paid' => (bool) $this->breakType?->is_paid,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'duration_minutes' => $inProgress ? null : (int) $this->duration_minutes,
            'overage_minutes' => $this->overageMinutes(),
            'in_progress' => $inProgress,
        ];
    }

    /**
     * Minutos de esta pausa que se descuentan del tiempo trabajado por exceder el límite:
     * solo aplica a pausas pagadas finalizadas con max_duration_minutes definido. 0 en otro caso.
     */
    public function overageMinutes(): int
    {
        if ($this->ended_at === null
            || ! (bool) $this->breakType?->is_paid
            || $this->breakType?->max_duration_minutes === null) {
            return 0;
        }

        return max(0, (int) $this->duration_minutes - (int) $this->breakType->max_duration_minutes);
    }
}
