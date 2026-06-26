<?php

namespace App\Domain\Company\Models;

use App\Domain\Shared\Traits\BelongsToCompany;
use Database\Factories\SurchargeRuleFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurchargeRule extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): SurchargeRuleFactory
    {
        return SurchargeRuleFactory::new();
    }

    protected $fillable = [
        'company_id',
        'night_surcharge',
        'overtime_day',
        'overtime_night',
        'sunday_holiday',
        'overtime_day_sunday',
        'overtime_night_sunday',
        'night_sunday',
        'pay_overtime_by_default',
        'max_weekly_minutes',
        'max_daily_minutes',
        'night_start_time',
        'night_end_time',
        'default_monthly_salary',
        'default_hourly_rate',
        'transport_allowance',
        'dominical_weekday',
        'pay_dominical_by_default',
        'pay_night_dominical',
        'pay_night_holiday',
        'pay_overtime_dominical',
        'pay_overtime_holiday',
        'pay_overtime_night',
        'default_dominical_payment_mode',
        'default_normal_day_value',
        'default_holiday_payment_mode',
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
            'pay_overtime_by_default' => 'boolean',
            'max_weekly_minutes' => 'integer',
            'max_daily_minutes' => 'integer',
            'default_monthly_salary' => 'decimal:2',
            'default_hourly_rate' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'dominical_weekday' => 'integer',
            'pay_dominical_by_default' => 'boolean',
            'pay_night_dominical' => 'boolean',
            'pay_night_holiday' => 'boolean',
            'pay_overtime_dominical' => 'boolean',
            'pay_overtime_holiday' => 'boolean',
            'pay_overtime_night' => 'boolean',
            'default_normal_day_value' => 'decimal:2',
        ];
    }

    /**
     * Normalize TIME columns to H:i format regardless of DB driver.
     * MySQL returns '22:00:00'; SQLite returns '22:00'. Both normalize to '22:00'.
     */
    protected function nightStartTime(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => substr($value, 0, 5),
        );
    }

    protected function nightEndTime(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => substr($value, 0, 5),
        );
    }
}
