<?php

namespace Tests\Unit;

use App\Domain\Company\Models\SurchargeRule;
use App\Domain\TimeTracking\Actions\CalculateReportCosts;
use PHPUnit\Framework\TestCase;

class CalculateReportCostsTest extends TestCase
{
    private CalculateReportCosts $calculator;

    private SurchargeRule $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new CalculateReportCosts;

        // Reglas estándar colombianas
        $this->rules = new SurchargeRule([
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_hours' => 42,
        ]);
    }

    public function test_regular_hours_have_no_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'night_hours' => 0,
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 0,
        ], $this->rules);

        $this->assertEquals(80000.0, $result['regular']);
        $this->assertEquals(0.0, $result['night']);
        $this->assertEquals(0.0, $result['overtime']);
        $this->assertEquals(0.0, $result['sunday_holiday']);
        $this->assertEquals(80000.0, $result['total']);
    }

    public function test_night_hours_apply_35_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 0,
            'night_hours' => 4.0,
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 0,
        ], $this->rules);

        // 4h × $10,000 × 1.35 = $54,000
        $this->assertEquals(54000.0, $result['night']);
        $this->assertEquals(54000.0, $result['total']);
    }

    public function test_overtime_hours_apply_25_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 0,
            'night_hours' => 0,
            'overtime_hours' => 2.0,
            'sunday_holiday_hours' => 0,
        ], $this->rules);

        // 2h × $10,000 × 1.25 = $25,000
        $this->assertEquals(25000.0, $result['overtime']);
        $this->assertEquals(25000.0, $result['total']);
    }

    public function test_sunday_holiday_hours_apply_75_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 0,
            'night_hours' => 0,
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 6.0,
        ], $this->rules);

        // 6h × $10,000 × 1.75 = $105,000
        $this->assertEquals(105000.0, $result['sunday_holiday']);
        $this->assertEquals(105000.0, $result['total']);
    }

    public function test_total_cost_is_sum_of_all_types(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'night_hours' => 2.0,
            'overtime_hours' => 1.0,
            'sunday_holiday_hours' => 1.0,
        ], $this->rules);

        $expected = 80000 + 27000 + 12500 + 17500; // = 137,000
        $this->assertEquals($expected, $result['total']);
    }

    public function test_zero_hourly_rate_returns_zero_cost(): void
    {
        $result = $this->calculator->execute(0, [
            'regular_hours' => 8.0,
            'night_hours' => 4.0,
            'overtime_hours' => 2.0,
            'sunday_holiday_hours' => 1.0,
        ], $this->rules);

        $this->assertEquals(0.0, $result['total']);
        $this->assertEquals(0.0, $result['regular']);
    }

    public function test_zero_hours_returns_zero_cost(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 0,
            'night_hours' => 0,
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 0,
        ], $this->rules);

        $this->assertEquals(0.0, $result['total']);
    }

    public function test_details_array_contains_correct_surcharge_percentages(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 1.0,
            'night_hours' => 1.0,
            'overtime_hours' => 1.0,
            'sunday_holiday_hours' => 1.0,
        ], $this->rules);

        $this->assertCount(4, $result['details']);
        $this->assertEquals(0, $result['details'][0]['surcharge']); // regular
        $this->assertEquals(35, $result['details'][1]['surcharge']); // night
        $this->assertEquals(25, $result['details'][2]['surcharge']); // overtime
        $this->assertEquals(75, $result['details'][3]['surcharge']); // sunday_holiday
    }

    public function test_custom_surcharge_rules_are_applied(): void
    {
        $customRules = new SurchargeRule([
            'night_surcharge' => 50,
            'overtime_day' => 40,
            'sunday_holiday' => 100,
        ]);

        $result = $this->calculator->execute(10000, [
            'regular_hours' => 0,
            'night_hours' => 1.0,
            'overtime_hours' => 1.0,
            'sunday_holiday_hours' => 1.0,
        ], $customRules);

        // night: 1 × 10000 × 1.50 = 15000
        $this->assertEquals(15000.0, $result['night']);
        // overtime: 1 × 10000 × 1.40 = 14000
        $this->assertEquals(14000.0, $result['overtime']);
        // sunday: 1 × 10000 × 2.00 = 20000
        $this->assertEquals(20000.0, $result['sunday_holiday']);
    }

    public function test_missing_hour_keys_default_to_zero(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 5.0,
        ], $this->rules);

        $this->assertEquals(50000.0, $result['regular']);
        $this->assertEquals(0.0, $result['night']);
        $this->assertEquals(0.0, $result['overtime']);
        $this->assertEquals(0.0, $result['sunday_holiday']);
        $this->assertEquals(50000.0, $result['total']);
    }
}
