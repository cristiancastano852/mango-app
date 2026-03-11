<?php

namespace App\Domain\Company\Models;

use App\Domain\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurchargeRule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'night_surcharge',
        'overtime_day',
        'overtime_night',
        'sunday_holiday',
        'overtime_day_sunday',
        'overtime_night_sunday',
        'night_sunday',
        'max_weekly_hours',
        'night_start_time',
        'night_end_time',
    ];

    protected function casts(): array
    {
        return [
            'night_surcharge' => 'decimal:2',
            'overtime_day' => 'decimal:2',
            'overtime_night' => 'decimal:2',
            'sunday_holiday' => 'decimal:2',
            'overtime_day_sunday' => 'decimal:2',
            'overtime_night_sunday' => 'decimal:2',
            'night_sunday' => 'decimal:2',
        ];
    }
}
