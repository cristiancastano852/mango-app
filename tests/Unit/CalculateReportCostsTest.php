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

    // ----------------------------------------------------------------------------------
    // Modo salario mensual (monthly): la hora ordinaria ya está en el salario base.
    // valor hora de referencia en estos casos: $8.000 (≈ SMLV 1.750.905 / 220 redondeado).
    // ----------------------------------------------------------------------------------

    public function test_monthly_regular_hours_do_not_add_cost(): void
    {
        // Quincena ordinaria pura: 96h diurnas (12 días × 8h), base $875.452,5 (media de SMLV 1.750.905).
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        // Las horas ordinarias no suman: el total es exactamente el salario base.
        $this->assertEquals(0.0, $result['regular']);
        $this->assertEquals(875452.5, $result['base']);
        $this->assertEquals(875452.5, $result['total']);
        $this->assertEquals('monthly', $result['salary_type']);
    }

    public function test_monthly_night_surcharge_adds_only_the_percentage(): void
    {
        // 10 horas nocturnas, recargo 35%. Solo el excedente: 10 × 8000 × 0.35 = 28.000.
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 86.0,
            'night_hours' => 10.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $this->assertEquals(0.0, $result['regular']);
        $this->assertEquals(28000.0, $result['night']);
        // total = base + solo el recargo nocturno
        $this->assertEquals(875452.5 + 28000.0, $result['total']);
    }

    public function test_monthly_sunday_and_night_sunday_add_only_the_percentage(): void
    {
        // Dominical diurno 8h (75%): 8 × 8000 × 0.75 = 48.000
        // Dominical nocturno 4h (110%): 4 × 8000 × 1.10 = 35.200
        $result = $this->calculator->execute(8000, [
            'sunday_holiday_hours' => 8.0,
            'night_sunday_hours' => 4.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0);

        $this->assertEquals(48000.0, $result['sunday_holiday']);
        $this->assertEquals(35200.0, $result['night_sunday']);
        $this->assertEquals(1000000.0 + 48000.0 + 35200.0, $result['total']);
    }

    public function test_monthly_overtime_is_paid_at_full_value(): void
    {
        // Las horas extra sí se pagan completas (fuera de la jornada base).
        // 5 extra diurnas (25%): 5 × 8000 × 1.25 = 50.000
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
            'overtime_day_hours' => 5.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $this->assertEquals(0.0, $result['regular']);
        $this->assertEquals(50000.0, $result['overtime_day']);
        $this->assertEquals(875452.5 + 50000.0, $result['total']);
    }

    public function test_monthly_full_quincena_with_mixed_concepts(): void
    {
        // Quincena realista: base + recargos (solo %) + extras (completas).
        // base 1.000.000
        // night 12h × 8000 × 0.35 = 33.600
        // sunday_holiday 8h × 8000 × 0.75 = 48.000
        // overtime_day 6h × 8000 × 1.25 = 60.000
        // overtime_night 2h × 8000 × 1.75 = 28.000
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 90.0,
            'night_hours' => 12.0,
            'sunday_holiday_hours' => 8.0,
            'overtime_day_hours' => 6.0,
            'overtime_night_hours' => 2.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0);

        $this->assertEquals(0.0, $result['regular']);
        $this->assertEquals(33600.0, $result['night']);
        $this->assertEquals(48000.0, $result['sunday_holiday']);
        $this->assertEquals(60000.0, $result['overtime_day']);
        $this->assertEquals(28000.0, $result['overtime_night']);

        $expected = 1000000.0 + 33600.0 + 48000.0 + 60000.0 + 28000.0;
        $this->assertEquals($expected, $result['total']);
    }

    public function test_monthly_same_base_regardless_of_days_worked(): void
    {
        // Febrero (menos días) y octubre (más días): si solo trabajó la jornada ordinaria,
        // el total es el mismo salario base, sin importar las horas ordinarias acumuladas.
        $february = $this->calculator->execute(8000, [
            'regular_hours' => 88.0, // menos días trabajados
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $october = $this->calculator->execute(8000, [
            'regular_hours' => 104.0, // más días trabajados
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $this->assertEquals($february['total'], $october['total']);
        $this->assertEquals(875452.5, $february['total']);
    }

    public function test_monthly_unpaid_overtime_keeps_base_and_surcharges(): void
    {
        // payOvertime=false en modo monthly: las extra van a 0, pero base y recargos suman.
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 90.0,
            'night_hours' => 10.0,
            'overtime_day_hours' => 5.0,
            'overtime_night_hours' => 3.0,
        ], $this->rules, payOvertime: false, salaryType: 'monthly', baseSalary: 1000000.0);

        $this->assertFalse($result['pay_overtime']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        // base + night (solo 35%) = 1.000.000 + 28.000
        $this->assertEquals(1000000.0 + 28000.0, $result['total']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertTrue($byType['overtime_day']['compensated']);
        $this->assertTrue($byType['overtime_night']['compensated']);
    }

    public function test_hourly_mode_does_not_add_base_even_if_provided(): void
    {
        // En modo hourly el salario base se ignora aunque se pase por error.
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
        ], $this->rules, salaryType: 'hourly', baseSalary: 999999.0);

        $this->assertEquals(0.0, $result['base']);
        $this->assertEquals(80000.0, $result['total']);
        $this->assertEquals('hourly', $result['salary_type']);
    }
}
