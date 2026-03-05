<?php

namespace App\Domain\Employee\Models;

use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Position;
use App\Domain\Organization\Models\Schedule;
use App\Domain\Shared\Traits\BelongsToCompany;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'department_id',
        'position_id',
        'employee_code',
        'hire_date',
        'hourly_rate',
        'salary_type',
        'schedule_id',
        'location_id',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(BreakEntry::class);
    }
}
