<?php

namespace App\Domain\Organization\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'start_time',
        'end_time',
        'break_duration',
        'days_of_week',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
