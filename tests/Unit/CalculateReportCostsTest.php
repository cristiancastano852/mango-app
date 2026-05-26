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
        ], $this->rules);

        $this->assertEquals(80000.0, $result['regular']);
        $this->assertEquals(0.0, $result['night']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['sunday_holiday']);
        $this->assertEquals(80000.0, $result['total']);
    }

    public function test_night_hours_apply_35_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules);

        // 4h × $10,000 × 1.35 = $54,000
        $this->assertEquals(54000.0, $result['night']);
        $this->assertEquals(54000.0, $result['total']);
    }

    public function test_overtime_day_hours_apply_25_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 2.0,
        ], $this->rules);

        // 2h × $10,000 × 1.25 = $25,000
        $this->assertEquals(25000.0, $result['overtime_day']);
        $this->assertEquals(25000.0, $result['total']);
    }

    public function test_sunday_holiday_hours_apply_75_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'sunday_holiday_hours' => 6.0,
        ], $this->rules);

        // 6h × $10,000 × 1.75 = $105,000
        $this->assertEquals(105000.0, $result['sunday_holiday']);
        $this->assertEquals(105000.0, $result['total']);
    }

    public function test_overtime_night_applies_75_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_night_hours' => 2.0,
        ], $this->rules);

        // 2h × $10,000 × 1.75 = $35,000
        $this->assertEquals(35000.0, $result['overtime_night']);
        $this->assertEquals(35000.0, $result['total']);
    }

    public function test_night_sunday_applies_110_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'night_sunday_hours' => 2.0,
        ], $this->rules);

        // 2h × $10,000 × 2.10 = $42,000
        $this->assertEquals(42000.0, $result['night_sunday']);
        $this->assertEquals(42000.0, $result['total']);
    }

    public function test_overtime_day_sunday_applies_100_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_day_sunday_hours' => 2.0,
        ], $this->rules);

        // 2h × $10,000 × 2.00 = $40,000
        $this->assertEquals(40000.0, $result['overtime_day_sunday']);
        $this->assertEquals(40000.0, $result['total']);
    }

    public function test_overtime_night_sunday_applies_150_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_night_sunday_hours' => 2.0,
        ], $this->rules);

        // 2h × $10,000 × 2.50 = $50,000
        $this->assertEquals(50000.0, $result['overtime_night_sunday']);
        $this->assertEquals(50000.0, $result['total']);
    }

    public function test_total_cost_sums_all_8_types(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'night_hours' => 2.0,
            'sunday_holiday_hours' => 1.0,
            'night_sunday_hours' => 1.0,
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 1.0,
            'overtime_day_sunday_hours' => 1.0,
            'overtime_night_sunday_hours' => 1.0,
        ], $this->rules);

        // 80000 + 27000 + 17500 + 21000 + 12500 + 17500 + 20000 + 25000 = 220500
        $expected = 80000 + 27000 + 17500 + 21000 + 12500 + 17500 + 20000 + 25000;
        $this->assertEquals($expected, $result['total']);
    }

    public function test_zero_hourly_rate_returns_zero_cost(): void
    {
        $result = $this->calculator->execute(0, [
            'regular_hours' => 8.0,
            'night_hours' => 4.0,
            'overtime_day_hours' => 2.0,
            'sunday_holiday_hours' => 1.0,
        ], $this->rules);

        $this->assertEquals(0.0, $result['total']);
        $this->assertEquals(0.0, $result['regular']);
    }

    public function test_zero_hours_returns_zero_cost(): void
    {
        $result = $this->calculator->execute(10000, [], $this->rules);

        $this->assertEquals(0.0, $result['total']);
    }

    public function test_details_array_contains_8_items_with_correct_surcharges(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 1.0,
            'night_hours' => 1.0,
            'sunday_holiday_hours' => 1.0,
            'night_sunday_hours' => 1.0,
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 1.0,
            'overtime_day_sunday_hours' => 1.0,
            'overtime_night_sunday_hours' => 1.0,
        ], $this->rules);

        $this->assertCount(8, $result['details']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(0, $byType['regular']['surcharge']);
        $this->assertEquals(35, $byType['night']['surcharge']);
        $this->assertEquals(75, $byType['sunday_holiday']['surcharge']);
        $this->assertEquals(110, $byType['night_sunday']['surcharge']);
        $this->assertEquals(25, $byType['overtime_day']['surcharge']);
        $this->assertEquals(75, $byType['overtime_night']['surcharge']);
        $this->assertEquals(100, $byType['overtime_day_sunday']['surcharge']);
        $this->assertEquals(150, $byType['overtime_night_sunday']['surcharge']);
    }

    public function test_custom_surcharge_rules_are_applied(): void
    {
        $customRules = new SurchargeRule([
            'night_surcharge' => 50,
            'overtime_day' => 40,
            'sunday_holiday' => 100,
            'overtime_night' => 80,
            'night_sunday' => 120,
            'overtime_day_sunday' => 110,
            'overtime_night_sunday' => 160,
        ]);

        $result = $this->calculator->execute(10000, [
            'night_hours' => 1.0,
            'overtime_day_hours' => 1.0,
            'sunday_holiday_hours' => 1.0,
        ], $customRules);

        $this->assertEquals(15000.0, $result['night']);        // 1 × 10000 × 1.50
        $this->assertEquals(14000.0, $result['overtime_day']); // 1 × 10000 × 1.40
        $this->assertEquals(20000.0, $result['sunday_holiday']); // 1 × 10000 × 2.00
    }

    public function test_missing_hour_keys_default_to_zero(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 5.0,
        ], $this->rules);

        $this->assertEquals(50000.0, $result['regular']);
        $this->assertEquals(0.0, $result['night']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['sunday_holiday']);
        $this->assertEquals(0.0, $result['night_sunday']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day_sunday']);
        $this->assertEquals(0.0, $result['overtime_night_sunday']);
        $this->assertEquals(50000.0, $result['total']);
    }

    public function test_pay_overtime_defaults_to_true(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 2.0,
        ], $this->rules);

        $this->assertTrue($result['pay_overtime']);
        $this->assertEquals(25000.0, $result['overtime_day']);
        $this->assertEquals(25000.0, $result['total']);
    }

    public function test_unpaid_overtime_zeroes_the_four_overtime_costs_and_excludes_from_total(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'overtime_day_hours' => 2.0,
            'overtime_night_hours' => 2.0,
            'overtime_day_sunday_hours' => 2.0,
            'overtime_night_sunday_hours' => 2.0,
        ], $this->rules, payOvertime: false);

        $this->assertFalse($result['pay_overtime']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day_sunday']);
        $this->assertEquals(0.0, $result['overtime_night_sunday']);

        // Solo las 8 horas ordinarias suman al total.
        $this->assertEquals(80000.0, $result['total']);
    }

    public function test_unpaid_overtime_keeps_hours_visible_in_details(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_night_hours' => 8.0,
        ], $this->rules, payOvertime: false);

        $byType = collect($result['details'])->keyBy('type');

        // Las horas trabajadas se siguen mostrando aunque el costo sea 0.
        $this->assertEquals(8.0, $byType['overtime_night']['hours']);
        $this->assertEquals(75, $byType['overtime_night']['surcharge']);
        $this->assertEquals(0.0, $byType['overtime_night']['subtotal']);
        $this->assertTrue($byType['overtime_night']['compensated']);
    }

    public function test_unpaid_overtime_does_not_affect_non_overtime_costs(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'night_hours' => 2.0,
            'sunday_holiday_hours' => 1.0,
            'night_sunday_hours' => 1.0,
            'overtime_day_hours' => 5.0,
        ], $this->rules, payOvertime: false);

        // 80000 + 27000 + 17500 + 21000 = 145500 (sin overtime)
        $this->assertEquals(80000.0, $result['regular']);
        $this->assertEquals(27000.0, $result['night']);
        $this->assertEquals(17500.0, $result['sunday_holiday']);
        $this->assertEquals(21000.0, $result['night_sunday']);
        $this->assertEquals(145500.0, $result['total']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertFalse($byType['regular']['compensated']);
        $this->assertFalse($byType['night']['compensated']);
    }
}
