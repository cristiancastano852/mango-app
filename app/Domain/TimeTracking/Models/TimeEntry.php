<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeEntry extends Model
{
    use BelongsToCompany, HasFactory;

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
        'overtime_hours',
        'night_hours',
        'sunday_holiday_hours',
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
            'regular_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'sunday_holiday_hours' => 'decimal:2',
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
}
