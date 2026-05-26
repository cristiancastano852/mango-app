<?php

namespace App\Domain\Company\Models;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Traits\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimePaymentDecision extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'employee_id',
        'start_date',
        'end_date',
        'pay_overtime',
        'exported_by',
        'exported_at',
    ];

    protected function casts(): array
    {
        return [
            'pay_overtime' => 'boolean',
            'exported_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
