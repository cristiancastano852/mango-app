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

        // Reglas estándar colombianas. Las columnas de % (sunday_holiday, night_sunday,
        // overtime_*_sunday) son el recargo compartido por dominical y festivo.
        $this->rules = new SurchargeRule([
            'night_surcharge' => 35,
            'overtime_day' => 25,
            'overtime_night' => 75,
            'sunday_holiday' => 75,
            'overtime_day_sunday' => 100,
            'overtime_night_sunday' => 150,
            'night_sunday' => 110,
            'max_weekly_minutes' => 2520,
        ]);
    }

    public function test_regular_hours_have_no_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
        ], $this->rules);

        $this->assertEquals(80000.0, $result['regular']);
        $this->assertEquals(0.0, $result['night']);
        $this->assertEquals(0.0, $result['dominical']);
        $this->assertEquals(80000.0, $result['total']);
    }

    public function test_night_hours_apply_35_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules);

        $this->assertEquals(54000.0, $result['night']); // 4 × 10000 × 1.35
        $this->assertEquals(54000.0, $result['total']);
    }

    public function test_dominical_hours_apply_75_percent_in_hour_mode(): void
    {
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 6.0,
        ], $this->rules);

        $this->assertEquals(105000.0, $result['dominical']); // 6 × 10000 × 1.75
        $this->assertEquals(105000.0, $result['total']);
    }

    public function test_holiday_hours_apply_same_percentage_as_dominical(): void
    {
        $result = $this->calculator->execute(10000, [
            'holiday_hours' => 6.0,
            'night_holiday_hours' => 2.0,
        ], $this->rules);

        $this->assertEquals(105000.0, $result['holiday']);       // 6 × 10000 × 1.75
        $this->assertEquals(42000.0, $result['night_holiday']);  // 2 × 10000 × 2.10
        $this->assertEquals(147000.0, $result['total']);
    }

    public function test_night_dominical_applies_110_percent_surcharge(): void
    {
        $result = $this->calculator->execute(10000, [
            'night_dominical_hours' => 2.0,
        ], $this->rules);

        $this->assertEquals(42000.0, $result['night_dominical']); // 2 × 10000 × 2.10
        $this->assertEquals(42000.0, $result['total']);
    }

    public function test_overtime_families_apply_their_surcharges(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 2.0,
            'overtime_night_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
            'overtime_night_dominical_hours' => 2.0,
            'overtime_day_holiday_hours' => 2.0,
            'overtime_night_holiday_hours' => 2.0,
        ], $this->rules);

        $this->assertEquals(25000.0, $result['overtime_day']);              // ×1.25
        $this->assertEquals(35000.0, $result['overtime_night']);            // ×1.75
        $this->assertEquals(40000.0, $result['overtime_day_dominical']);    // ×2.00
        $this->assertEquals(50000.0, $result['overtime_night_dominical']);  // ×2.50
        $this->assertEquals(40000.0, $result['overtime_day_holiday']);      // ×2.00
        $this->assertEquals(50000.0, $result['overtime_night_holiday']);    // ×2.50
    }

    public function test_details_array_contains_12_items_with_correct_surcharges(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 1.0,
            'night_hours' => 1.0,
            'dominical_hours' => 1.0,
            'night_dominical_hours' => 1.0,
            'holiday_hours' => 1.0,
            'night_holiday_hours' => 1.0,
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 1.0,
            'overtime_day_dominical_hours' => 1.0,
            'overtime_night_dominical_hours' => 1.0,
            'overtime_day_holiday_hours' => 1.0,
            'overtime_night_holiday_hours' => 1.0,
        ], $this->rules);

        $this->assertCount(12, $result['details']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(0, $byType['regular']['surcharge']);
        $this->assertEquals(35, $byType['night']['surcharge']);
        $this->assertEquals(75, $byType['dominical']['surcharge']);
        $this->assertEquals(110, $byType['night_dominical']['surcharge']);
        $this->assertEquals(75, $byType['holiday']['surcharge']);
        $this->assertEquals(110, $byType['night_holiday']['surcharge']);
        $this->assertEquals(100, $byType['overtime_day_dominical']['surcharge']);
        $this->assertEquals(150, $byType['overtime_night_holiday']['surcharge']);
    }

    public function test_zero_hours_returns_zero_cost(): void
    {
        $result = $this->calculator->execute(10000, [], $this->rules);

        $this->assertEquals(0.0, $result['total']);
        $this->assertCount(12, $result['details']);
    }

    // ---------------------------------------------------------------------------
    // Modo por día: el valor fijo es SOLO el recargo (plus plano). La base de las
    // horas se paga como ordinario/nocturno y encima min(K,N) × day_value.
    // ---------------------------------------------------------------------------

    public function test_day_mode_adds_base_plus_flat_premium_hourly(): void
    {
        // 12h dominicales diurnas (2 días de 6h), tarifa 10000, día = 60000, paga los 2.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 12.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => null,
        ]);

        // base 12×10000=120000 + plus 2×60000=120000
        $this->assertEquals(240000.0, $result['dominical']);
        $this->assertEquals(2, $result['dominical_paid_days']);
        $this->assertEquals(240000.0, $result['total']);
    }

    public function test_day_mode_with_k_less_than_n_pays_fewer_premiums(): void
    {
        // 3 días trabajados, paga 2. base se mantiene; solo 2 plus.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 18.0, // 3 días × 6h
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 3,
            'payable_count' => 2,
        ]);

        // base 18×10000=180000 + plus 2×60000=120000
        $this->assertEquals(300000.0, $result['dominical']);
        $this->assertEquals(2, $result['dominical_paid_days']);
    }

    public function test_day_mode_monthly_only_adds_flat_premium(): void
    {
        // Mensual: la base ya está en el salario; solo entra el plus plano.
        $result = $this->calculator->execute(8000, [
            'dominical_hours' => 12.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => null,
        ]);

        $this->assertEquals(120000.0, $result['dominical']); // solo 2×60000
        $this->assertEquals(1000000.0 + 120000.0, $result['total']);
    }

    public function test_day_mode_keeps_night_surcharge_on_night_dominical_hours(): void
    {
        // En día, la base nocturna conserva el recargo nocturno; el plus dominical va aparte.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 6.0,
            'night_dominical_hours' => 2.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 1,
            'payable_count' => null,
        ]);

        // dominical = base 6×10000 + plus 1×60000 = 120000
        $this->assertEquals(120000.0, $result['dominical']);
        // night_dominical = 2 × 10000 × 1.35 (solo nocturno, sin dominical %)
        $this->assertEquals(27000.0, $result['night_dominical']);
    }

    // ---------------------------------------------------------------------------
    // Festivos siempre se pagan, independientes de la config dominical.
    // ---------------------------------------------------------------------------

    public function test_holiday_is_always_paid_even_when_dominical_disabled(): void
    {
        $result = $this->calculator->execute(10000, [
            'holiday_hours' => 4.0,
            'dominical_hours' => 4.0,
        ], $this->rules, dominical: [
            'pay' => false, // dominical apagado
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 1,
            'payable_count' => 0,
        ]);

        // Festivo paga su recargo: 4 × 10000 × 1.75 = 70000
        $this->assertEquals(70000.0, $result['holiday']);
        // Dominical apagado → base ordinaria: 4 × 10000 = 40000
        $this->assertEquals(40000.0, $result['dominical']);
    }

    // ---------------------------------------------------------------------------
    // Dominical no pagado: día normal (base ordinaria/nocturna), conserva nocturno.
    // ---------------------------------------------------------------------------

    public function test_unpaid_dominical_falls_back_to_regular_and_night(): void
    {
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 5.0,
            'night_dominical_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
        ], $this->rules, dominical: ['pay' => false]);

        // diurno → regular base
        $this->assertEquals(50000.0, $result['dominical']);          // 5 × 10000
        // nocturno → conserva recargo nocturno 35%
        $this->assertEquals(27000.0, $result['night_dominical']);    // 2 × 10000 × 1.35
        // overtime dominical → overtime de semana (25%) al tratarse como día normal
        $this->assertEquals(25000.0, $result['overtime_day_dominical']); // 2 × 10000 × 1.25

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(0, $byType['dominical']['surcharge']);
    }

    // ---------------------------------------------------------------------------
    // Overtime compensado: cubre las 6 categorías de overtime.
    // ---------------------------------------------------------------------------

    public function test_unpaid_overtime_zeroes_all_six_overtime_categories(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'overtime_day_hours' => 2.0,
            'overtime_night_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
            'overtime_night_dominical_hours' => 2.0,
            'overtime_day_holiday_hours' => 2.0,
            'overtime_night_holiday_hours' => 2.0,
        ], $this->rules, payOvertime: false);

        $this->assertFalse($result['pay_overtime']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day_dominical']);
        $this->assertEquals(0.0, $result['overtime_night_dominical']);
        $this->assertEquals(0.0, $result['overtime_day_holiday']);
        $this->assertEquals(0.0, $result['overtime_night_holiday']);
        $this->assertEquals(80000.0, $result['total']); // solo las ordinarias

        $byType = collect($result['details'])->keyBy('type');
        $this->assertTrue($byType['overtime_day_dominical']['compensated']);
        $this->assertTrue($byType['overtime_night_holiday']['compensated']);
        $this->assertFalse($byType['regular']['compensated']);
    }

    public function test_unpaid_overtime_keeps_hours_visible_in_details(): void
    {
        $result = $this->calculator->execute(10000, [
            'overtime_night_hours' => 8.0,
        ], $this->rules, payOvertime: false);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(8.0, $byType['overtime_night']['hours']);
        $this->assertEquals(0.0, $byType['overtime_night']['subtotal']);
        $this->assertTrue($byType['overtime_night']['compensated']);
    }

    // ---------------------------------------------------------------------------
    // Modo salario mensual (la hora ordinaria ya está en el salario base).
    // ---------------------------------------------------------------------------

    public function test_monthly_regular_hours_do_not_add_cost(): void
    {
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $this->assertEquals(0.0, $result['regular']);
        $this->assertEquals(875452.5, $result['base']);
        $this->assertEquals(875452.5, $result['total']);
        $this->assertEquals('monthly', $result['salary_type']);
    }

    public function test_monthly_dominical_and_night_dominical_add_only_the_percentage(): void
    {
        $result = $this->calculator->execute(8000, [
            'dominical_hours' => 8.0,
            'night_dominical_hours' => 4.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0);

        $this->assertEquals(48000.0, $result['dominical']);       // 8 × 8000 × 0.75
        $this->assertEquals(35200.0, $result['night_dominical']); // 4 × 8000 × 1.10
        $this->assertEquals(1000000.0 + 48000.0 + 35200.0, $result['total']);
    }

    public function test_monthly_overtime_is_paid_at_full_value(): void
    {
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
            'overtime_day_hours' => 5.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5);

        $this->assertEquals(50000.0, $result['overtime_day']); // 5 × 8000 × 1.25
        $this->assertEquals(875452.5 + 50000.0, $result['total']);
    }

    public function test_hourly_mode_does_not_add_base_even_if_provided(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
        ], $this->rules, salaryType: 'hourly', baseSalary: 999999.0);

        $this->assertEquals(0.0, $result['base']);
        $this->assertEquals(80000.0, $result['total']);
    }

    // ---------------------------------------------------------------------------
    // Auxilio de transporte (solo monthly, plano).
    // ---------------------------------------------------------------------------

    public function test_monthly_transport_allowance_adds_to_total(): void
    {
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 875452.5, transportAllowance: 124547.5);

        $this->assertEquals(124547.5, $result['transport_allowance']);
        $this->assertEquals(875452.5 + 124547.5, $result['total']);
    }

    public function test_hourly_mode_ignores_transport_allowance(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
        ], $this->rules, salaryType: 'hourly', baseSalary: 0.0, transportAllowance: 999999.0);

        $this->assertEquals(0.0, $result['transport_allowance']);
        $this->assertEquals(80000.0, $result['total']);
    }
}
