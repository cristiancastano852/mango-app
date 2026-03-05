<?php

namespace App\Domain\TimeTracking\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreakEntry extends Model
{
    use BelongsToCompany, HasFactory;

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
}
