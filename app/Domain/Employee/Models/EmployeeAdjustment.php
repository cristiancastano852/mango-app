<?php

namespace App\Domain\Employee\Models;

use App\Domain\Employee\Enums\AdjustmentType;
use App\Domain\Shared\Traits\BelongsToCompany;
use App\Models\User;
use Database\Factories\EmployeeAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdjustment extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): EmployeeAdjustmentFactory
    {
        return EmployeeAdjustmentFactory::new();
    }

    protected $fillable = [
        'company_id',
        'employee_id',
        'date',
        'type',
        'amount',
        'concept',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => AdjustmentType::class,
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
