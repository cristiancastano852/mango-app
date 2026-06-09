<?php

namespace Database\Factories;

use App\Domain\Company\Models\SurchargeRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Company\Models\SurchargeRule>
 */
class SurchargeRuleFactory extends Factory
{
    protected $model = SurchargeRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $smlv = (float) config('payroll.smlv_monthly');
        $divisor = max((int) config('payroll.hourly_divisor'), 1);

        return [
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'pay_overtime_by_default' => true,
            'max_weekly_hours' => 42,
            'max_daily_hours' => 8,
            'night_start_time' => '21:00',
            'night_end_time' => '06:00',
            'default_monthly_salary' => $smlv,
            'default_hourly_rate' => round($smlv / $divisor, 2),
            'transport_allowance' => (float) config('payroll.transport_allowance_monthly'),
        ];
    }
}
