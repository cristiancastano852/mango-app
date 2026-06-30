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
    // Modo por día: day_value es el VALOR DEL DÍA NORMAL; el recargo por día pagado =
    // valor_día × sunday_holiday% (75%). La base de las horas se paga como ordinario/nocturno.
    // ---------------------------------------------------------------------------

    public function test_day_mode_adds_base_plus_flat_premium_hourly(): void
    {
        // 12h dominicales diurnas (2 días de 6h), tarifa 10000, valor día = 60000, paga los 2.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 12.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => null,
        ]);

        // base 12×10000=120000 + plus 2×(60000×0.75)=90000 → 210000
        $this->assertEquals(210000.0, $result['dominical']);
        $this->assertEquals(2, $result['dominical_paid_days']);
        $this->assertEquals(210000.0, $result['total']);
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

        // base 18×10000=180000 + plus 2×(60000×0.75)=90000 → 270000
        $this->assertEquals(270000.0, $result['dominical']);
        $this->assertEquals(2, $result['dominical_paid_days']);
    }

    public function test_day_mode_monthly_only_adds_flat_premium(): void
    {
        // Mensual: la base ya está en el salario; solo entra el plus (valor día × %).
        $result = $this->calculator->execute(8000, [
            'dominical_hours' => 12.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => null,
        ]);

        $this->assertEquals(90000.0, $result['dominical']); // 2×(60000×0.75)
        $this->assertEquals(1000000.0 + 90000.0, $result['total']);
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

        // dominical = base 6×10000 + plus 1×(60000×0.75)=45000 → 105000
        $this->assertEquals(105000.0, $result['dominical']);
        // night_dominical = 2 × 10000 × 1.35 (solo nocturno, sin dominical %)
        $this->assertEquals(27000.0, $result['night_dominical']);
    }

    public function test_day_mode_count_drives_premium_even_when_switch_off(): void
    {
        // Empresa con pagar-dominicales OFF, pero modo día: el conteo K manda. Si elige pagar 2,
        // se pagan 2 recargos aunque el default sea no pagar.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 18.0,
        ], $this->rules, dominical: [
            'pay' => false,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 3,
            'payable_count' => 2,
        ]);

        // base 18×10000=180000 + 2×(60000×0.75)=90000 → 270000
        $this->assertEquals(270000.0, $result['dominical']);
        $this->assertEquals(2, $result['dominical_paid_days']);
    }

    public function test_day_mode_count_can_exceed_worked_days(): void
    {
        // Solo 2 dominicales trabajados, pero el admin paga 3 (salda uno pendiente de otra quincena).
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 12.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => 3,
        ]);

        // base 12×10000=120000 + 3×(60000×0.75)=135000 → 255000
        $this->assertEquals(255000.0, $result['dominical']);
        $this->assertEquals(3, $result['dominical_paid_days']);
    }

    public function test_day_mode_switch_off_defaults_to_zero_paid_days(): void
    {
        // Sin decisión explícita y switch OFF → ningún recargo (default 0), solo base.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 12.0,
        ], $this->rules, dominical: [
            'pay' => false,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 2,
            'payable_count' => null,
        ]);

        $this->assertEquals(120000.0, $result['dominical']); // solo base 12×10000
        $this->assertEquals(0, $result['dominical_paid_days']);
    }

    // ---------------------------------------------------------------------------
    // Festivos siempre se pagan, independientes de la config dominical.
    // ---------------------------------------------------------------------------

    public function test_holiday_hour_mode_pays_per_hour(): void
    {
        // Modo hora (default): festivo = horas × tarifa × (1 + 75%).
        $result = $this->calculator->execute(10000, [
            'holiday_hours' => 6.0,
            'night_holiday_hours' => 2.0,
        ], $this->rules, holiday: ['mode' => 'hour', 'worked_days' => 1]);

        $this->assertEquals(105000.0, $result['holiday']);       // 6 × 10000 × 1.75
        $this->assertEquals(42000.0, $result['night_holiday']);  // 2 × 10000 × 2.10
    }

    public function test_holiday_day_mode_adds_base_plus_flat_premium(): void
    {
        // Modo día: base por horas como ordinario + recargo plano por cada festivo (valor día × 75%).
        // 2 festivos (12h diurnas), valor día 60000, recargo 75%.
        $result = $this->calculator->execute(10000, [
            'holiday_hours' => 12.0,
        ], $this->rules, holiday: ['mode' => 'day', 'day_value' => 60000, 'worked_days' => 2]);

        // base 12×10000=120000 + 2×(60000×0.75)=90000 → 210000
        $this->assertEquals(210000.0, $result['holiday']);
        $this->assertEquals('day', $result['holiday_mode']);
        $this->assertEquals(2, $result['holiday_worked_days']);
    }

    public function test_holiday_day_mode_monthly_only_adds_premium(): void
    {
        $result = $this->calculator->execute(8000, [
            'holiday_hours' => 12.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0, holiday: ['mode' => 'day', 'day_value' => 60000, 'worked_days' => 2]);

        $this->assertEquals(90000.0, $result['holiday']); // solo 2×(60000×0.75)
        $this->assertEquals(1000000.0 + 90000.0, $result['total']);
    }

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
    // Dominical no pagado (diurno): pay_dominical_by_default solo afecta el DIURNO.
    // La noche y la extra dominical son independientes (siguen sus propios flags).
    // ---------------------------------------------------------------------------

    public function test_unpaid_dominical_only_collapses_the_diurnal_bucket(): void
    {
        // Flags premium en su default (true = pagar). Solo el diurno cae a regular.
        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 5.0,
            'night_dominical_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
        ], $this->rules, dominical: ['pay' => false]);

        // diurno → regular base
        $this->assertEquals(50000.0, $result['dominical']);          // 5 × 10000
        // nocturno dominical: independiente del switch diurno → conserva recargo 110%
        $this->assertEquals(42000.0, $result['night_dominical']);    // 2 × 10000 × 2.10
        // extra dominical: independiente → recargo dominical 100%
        $this->assertEquals(40000.0, $result['overtime_day_dominical']); // 2 × 10000 × 2.00

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(0, $byType['dominical']['surcharge']);
    }

    // ---------------------------------------------------------------------------
    // Colapso de recargos premium (cost-time): night_dominical/holiday → night,
    // overtime dominical/festivo → overtime de semana, según los 4 flags de empresa.
    // ---------------------------------------------------------------------------

    public function test_night_dominical_collapses_into_night_when_flag_off(): void
    {
        $this->rules->pay_night_dominical = false;

        $result = $this->calculator->execute(10000, [
            'night_hours' => 2.0,
            'night_dominical_hours' => 4.0,
        ], $this->rules);

        // (2 + 4) × 10000 × 1.35 = 81000 todo en el renglón nocturno
        $this->assertEquals(81000.0, $result['night']);
        $this->assertEquals(0.0, $result['night_dominical']);
        $this->assertEquals(81000.0, $result['total']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(6.0, $byType['night']['hours']);
        $this->assertEquals(0.0, $byType['night_dominical']['hours']);
    }

    public function test_night_holiday_collapses_into_night_when_flag_off(): void
    {
        $this->rules->pay_night_holiday = false;

        $result = $this->calculator->execute(10000, [
            'night_hours' => 1.0,
            'night_holiday_hours' => 3.0,
        ], $this->rules);

        // (1 + 3) × 10000 × 1.35 = 54000
        $this->assertEquals(54000.0, $result['night']);
        $this->assertEquals(0.0, $result['night_holiday']);
        $this->assertEquals(54000.0, $result['total']);
    }

    public function test_both_night_flags_off_collapse_dominical_and_holiday_into_night(): void
    {
        $this->rules->pay_night_dominical = false;
        $this->rules->pay_night_holiday = false;

        $result = $this->calculator->execute(10000, [
            'night_hours' => 2.0,
            'night_dominical_hours' => 3.0,
            'night_holiday_hours' => 5.0,
        ], $this->rules);

        // (2 + 3 + 5) × 10000 × 1.35 = 135000
        $this->assertEquals(135000.0, $result['night']);
        $this->assertEquals(0.0, $result['night_dominical']);
        $this->assertEquals(0.0, $result['night_holiday']);
        $this->assertEquals(135000.0, $result['total']);
    }

    public function test_overtime_dominical_collapses_into_week_overtime_when_flag_off(): void
    {
        $this->rules->pay_overtime_dominical = false;

        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 1.0,
            'overtime_day_dominical_hours' => 2.0,
            'overtime_night_dominical_hours' => 2.0,
        ], $this->rules);

        // diurnas: (1 + 2) × 10000 × 1.25 = 37500
        $this->assertEquals(37500.0, $result['overtime_day']);
        // nocturnas: (1 + 2) × 10000 × 1.75 = 52500
        $this->assertEquals(52500.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day_dominical']);
        $this->assertEquals(0.0, $result['overtime_night_dominical']);
        $this->assertEquals(37500.0 + 52500.0, $result['total']);
    }

    public function test_overtime_holiday_collapses_into_week_overtime_when_flag_off(): void
    {
        $this->rules->pay_overtime_holiday = false;

        $result = $this->calculator->execute(10000, [
            'overtime_day_holiday_hours' => 2.0,
            'overtime_night_holiday_hours' => 2.0,
        ], $this->rules);

        $this->assertEquals(25000.0, $result['overtime_day']);   // 2 × 10000 × 1.25
        $this->assertEquals(35000.0, $result['overtime_night']); // 2 × 10000 × 1.75
        $this->assertEquals(0.0, $result['overtime_day_holiday']);
        $this->assertEquals(0.0, $result['overtime_night_holiday']);
    }

    // ---------------------------------------------------------------------------
    // Recargo extra nocturno (pay_overtime_night): apagado paga TODA extra nocturna
    // (semana/dominical/festiva) como su extra diurna correspondiente.
    // ---------------------------------------------------------------------------

    public function test_overtime_night_collapses_into_overtime_day_when_flag_off(): void
    {
        $this->rules->pay_overtime_night = false;

        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 1.0,
            'overtime_night_hours' => 2.0,
        ], $this->rules);

        // (1 + 2) × 10000 × 1.25 = 37500 todo en extra diurna; la nocturna queda en 0.
        $this->assertEquals(37500.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(37500.0, $result['total']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(3.0, $byType['overtime_day']['hours']);
        $this->assertEquals(0.0, $byType['overtime_night']['hours']);
    }

    public function test_overtime_night_dominical_folds_into_day_dominical_when_flag_off(): void
    {
        // Extra nocturna apagada pero dominical encendido: la extra nocturna dominical se paga como
        // extra diurna dominical (100%), no como extra de semana.
        $this->rules->pay_overtime_night = false;
        $this->rules->pay_overtime_dominical = true;

        $result = $this->calculator->execute(10000, [
            'overtime_day_dominical_hours' => 1.0,
            'overtime_night_dominical_hours' => 2.0,
        ], $this->rules);

        // (1 + 2) × 10000 × 2.00 = 60000 en extra diurna dominical.
        $this->assertEquals(60000.0, $result['overtime_day_dominical']);
        $this->assertEquals(0.0, $result['overtime_night_dominical']);
        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(60000.0, $result['total']);
    }

    public function test_overtime_night_holiday_folds_into_day_holiday_when_flag_off(): void
    {
        $this->rules->pay_overtime_night = false;
        $this->rules->pay_overtime_holiday = true;

        $result = $this->calculator->execute(10000, [
            'overtime_day_holiday_hours' => 1.0,
            'overtime_night_holiday_hours' => 2.0,
        ], $this->rules);

        // (1 + 2) × 10000 × 2.00 = 60000 en extra diurna festiva.
        $this->assertEquals(60000.0, $result['overtime_day_holiday']);
        $this->assertEquals(0.0, $result['overtime_night_holiday']);
        $this->assertEquals(60000.0, $result['total']);
    }

    public function test_overtime_night_off_with_dominical_off_lands_in_week_day_overtime(): void
    {
        // Ambos apagados: la extra nocturna dominical primero colapsa a semana, y de ahí a extra
        // diurna de semana (25%).
        $this->rules->pay_overtime_night = false;
        $this->rules->pay_overtime_dominical = false;

        $result = $this->calculator->execute(10000, [
            'overtime_night_dominical_hours' => 2.0,
        ], $this->rules);

        // 2 × 10000 × 1.25 = 25000 en extra diurna de semana.
        $this->assertEquals(25000.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day_dominical']);
        $this->assertEquals(0.0, $result['overtime_night_dominical']);
        $this->assertEquals(25000.0, $result['total']);
    }

    public function test_overtime_night_off_does_not_resurrect_pay_when_compensated(): void
    {
        // payOvertime=false manda: el display se funde en extra diurna (compensado), pero el pago
        // sigue en $0.
        $this->rules->pay_overtime_night = false;

        $result = $this->calculator->execute(10000, [
            'overtime_night_hours' => 2.0,
        ], $this->rules, payOvertime: false);

        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['total']);

        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(2.0, $byType['overtime_day']['hours']);
        $this->assertEquals(0.0, $byType['overtime_night']['hours']);
        $this->assertTrue($byType['overtime_day']['compensated']);
    }

    public function test_overtime_night_on_keeps_night_overtime_row(): void
    {
        // Regresión: flag en true (default) mantiene la extra nocturna con su recargo nocturno.
        $this->rules->pay_overtime_night = true;

        $result = $this->calculator->execute(10000, [
            'overtime_night_hours' => 2.0,
        ], $this->rules);

        // 2 × 10000 × 1.75 = 35000
        $this->assertEquals(35000.0, $result['overtime_night']);
        $this->assertEquals(0.0, $result['overtime_day']);
    }

    public function test_all_premium_flags_true_keeps_current_behavior(): void
    {
        // Regresión: con los 4 flags en true (default), los premiums se pagan como tales.
        $this->rules->pay_night_dominical = true;
        $this->rules->pay_night_holiday = true;
        $this->rules->pay_overtime_dominical = true;
        $this->rules->pay_overtime_holiday = true;

        $result = $this->calculator->execute(10000, [
            'night_dominical_hours' => 2.0,
            'night_holiday_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
            'overtime_night_holiday_hours' => 2.0,
        ], $this->rules);

        $this->assertEquals(42000.0, $result['night_dominical']);       // 2 × 10000 × 2.10
        $this->assertEquals(42000.0, $result['night_holiday']);         // 2 × 10000 × 2.10
        $this->assertEquals(40000.0, $result['overtime_day_dominical']); // 2 × 10000 × 2.00
        $this->assertEquals(50000.0, $result['overtime_night_holiday']); // 2 × 10000 × 2.50
    }

    public function test_overtime_flag_off_does_not_resurrect_pay_when_overtime_compensated(): void
    {
        // payOvertime=false manda: aunque el flag dominical esté off, el overtime sigue en $0.
        // El display SÍ se funde (las horas van al renglón base), pero el pago no se "resucita".
        $this->rules->pay_overtime_dominical = false;

        $result = $this->calculator->execute(10000, [
            'overtime_day_dominical_hours' => 4.0,
        ], $this->rules, payOvertime: false);

        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_day_dominical']);
        $this->assertEquals(0.0, $result['total']);

        // Las horas se funden en el renglón base (extra de semana), compensadas; el premium queda en 0h.
        $byType = collect($result['details'])->keyBy('type');
        $this->assertEquals(4.0, $byType['overtime_day']['hours']);
        $this->assertEquals(0.0, $byType['overtime_day_dominical']['hours']);
        $this->assertTrue($byType['overtime_day']['compensated']);
    }

    public function test_night_dominical_flag_independent_from_diurnal_switch(): void
    {
        // pay_dominical_by_default(diurno)=false pero noche premium ON → noche con recargo 110%.
        $this->rules->pay_night_dominical = true;

        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 4.0,
            'night_dominical_hours' => 2.0,
        ], $this->rules, dominical: ['pay' => false]);

        $this->assertEquals(40000.0, $result['dominical']);       // diurno → regular 4×10000
        $this->assertEquals(42000.0, $result['night_dominical']); // noche → 2×10000×2.10
    }

    public function test_diurnal_paid_but_night_collapsed(): void
    {
        // pay_dominical_by_default(diurno)=true pero noche premium OFF → noche como night.
        $this->rules->pay_night_dominical = false;

        $result = $this->calculator->execute(10000, [
            'dominical_hours' => 4.0,
            'night_dominical_hours' => 2.0,
        ], $this->rules, dominical: ['pay' => true, 'mode' => 'hour']);

        $this->assertEquals(70000.0, $result['dominical']);     // diurno con recargo 4×10000×1.75
        $this->assertEquals(0.0, $result['night_dominical']);   // colapsada
        $this->assertEquals(27000.0, $result['night']);         // 2×10000×1.35
    }

    public function test_day_mode_night_flag_off_folds_into_night_and_overtime_collapses(): void
    {
        // Modo por día con flags apagados: la noche dominical se funde en el nocturno base (mismo
        // 35% que en su renglón, ahora unificado) y la extra dominical colapsa a extra de semana.
        $this->rules->pay_overtime_dominical = false;
        $this->rules->pay_night_dominical = false;

        $result = $this->calculator->execute(10000, [
            'night_dominical_hours' => 2.0,
            'overtime_day_dominical_hours' => 2.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 1,
        ]);

        // noche dominical fundida en 'night' (2×10000×1.35), su renglón queda en 0.
        $this->assertEquals(27000.0, $result['night']);
        $this->assertEquals(0.0, $result['night_dominical']);
        // overtime dominical colapsa a overtime de semana: 2×10000×1.25
        $this->assertEquals(25000.0, $result['overtime_day']);
        $this->assertEquals(0.0, $result['overtime_day_dominical']);
    }

    public function test_day_mode_night_flag_on_keeps_night_dominical_row(): void
    {
        // Modo por día con flag encendido: la noche dominical se muestra en su propio renglón a
        // tarifa nocturna normal (35%), sin fundirse.
        $this->rules->pay_night_dominical = true;

        $result = $this->calculator->execute(10000, [
            'night_hours' => 1.0,
            'night_dominical_hours' => 2.0,
        ], $this->rules, dominical: [
            'pay' => true,
            'mode' => 'day',
            'day_value' => 60000,
            'worked_days' => 1,
        ]);

        $this->assertEquals(10000.0 * 1.35, $result['night']);          // 1×10000×1.35
        $this->assertEquals(27000.0, $result['night_dominical']);       // 2×10000×1.35, renglón propio
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

    // ---------------------------------------------------------------------------
    // Seguridad social a cargo del empleado (4% salud + 4% pensión sobre el IBC).
    // ---------------------------------------------------------------------------

    private const SS = ['health' => 4.0, 'pension' => 4.0];

    public function test_social_security_base_in_monthly_excludes_transport_allowance(): void
    {
        $result = $this->calculator->execute(8000, [
            'regular_hours' => 96.0,
            'night_hours' => 4.0,
        ], $this->rules, salaryType: 'monthly', baseSalary: 1000000.0, transportAllowance: 124547.5, socialSecurity: self::SS);

        // total = base 1.000.000 + nocturno (4 × 8000 × 0.35 = 11.200) + auxilio 124.547,5
        $this->assertEquals(1135747.5, $result['total']);
        // IBC = total − auxilio = 1.011.200
        $this->assertEquals(1011200.0, $result['social_security_base']);
        $this->assertEquals(40448.0, $result['health_deduction']);  // 4%
        $this->assertEquals(40448.0, $result['pension_deduction']); // 4%
        $this->assertEquals(1135747.5 - 40448.0 - 40448.0, $result['net_pay']);
    }

    public function test_social_security_base_in_hourly_equals_total(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
            'overtime_day_hours' => 2.0,
        ], $this->rules, salaryType: 'hourly', socialSecurity: self::SS);

        // total = 8 × 10000 + 2 × 10000 × 1.25 = 80.000 + 25.000 = 105.000
        $this->assertEquals(105000.0, $result['total']);
        $this->assertEquals(105000.0, $result['social_security_base']);
        $this->assertEquals(4200.0, $result['health_deduction']);
        $this->assertEquals(4200.0, $result['pension_deduction']);
        $this->assertEquals(96600.0, $result['net_pay']);
    }

    public function test_social_security_is_zero_when_no_hours_worked(): void
    {
        $result = $this->calculator->execute(10000, [], $this->rules, socialSecurity: self::SS);

        $this->assertEquals(0.0, $result['total']);
        $this->assertEquals(0.0, $result['social_security_base']);
        $this->assertEquals(0.0, $result['health_deduction']);
        $this->assertEquals(0.0, $result['pension_deduction']);
        $this->assertEquals(0.0, $result['net_pay']);
    }

    public function test_social_security_rates_come_from_parameter(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 10.0,
        ], $this->rules, socialSecurity: ['health' => 4.0, 'pension' => 4.0]);

        // 8% total sobre 100.000 = 8.000 de deducción
        $this->assertEquals(100000.0, $result['social_security_base']);
        $this->assertEquals(4000.0, $result['health_deduction']);
        $this->assertEquals(4000.0, $result['pension_deduction']);
        $this->assertEquals(92000.0, $result['net_pay']);
    }

    public function test_social_security_defaults_to_zero_without_rates(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 8.0,
        ], $this->rules);

        $this->assertEquals(0.0, $result['health_deduction']);
        $this->assertEquals(0.0, $result['pension_deduction']);
        $this->assertEquals($result['total'], $result['net_pay']);
    }

    // ---------------------------------------------------------------------------
    // Ajustes de nómina (bonos/deducciones) aplicados después del neto a pagar.
    // ---------------------------------------------------------------------------

    public function test_adjustments_apply_after_net_pay(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 10.0,
        ], $this->rules, socialSecurity: self::SS, adjustments: ['bonus_total' => 100000, 'deduction_total' => 50000]);

        // total 100.000; neto = 100.000 − 4.000 − 4.000 = 92.000.
        $this->assertEquals(92000.0, $result['net_pay']);
        $this->assertEquals(100000.0, $result['bonus_total']);
        $this->assertEquals(50000.0, $result['deduction_total']);
        // final = neto + bono − deducción = 92.000 + 100.000 − 50.000 = 142.000.
        $this->assertEquals(142000.0, $result['final_pay']);
        // Los ajustes no tocan la base de seguridad social ni las deducciones.
        $this->assertEquals(100000.0, $result['social_security_base']);
        $this->assertEquals(4000.0, $result['health_deduction']);
        $this->assertEquals(4000.0, $result['pension_deduction']);
    }

    public function test_final_pay_equals_net_pay_without_adjustments(): void
    {
        $result = $this->calculator->execute(10000, [
            'regular_hours' => 10.0,
        ], $this->rules, socialSecurity: self::SS);

        $this->assertEquals(0.0, $result['bonus_total']);
        $this->assertEquals(0.0, $result['deduction_total']);
        $this->assertEquals($result['net_pay'], $result['final_pay']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // overtime_payable_hours — cap sobre bolsa única (3 flags premium en off)
    // ──────────────────────────────────────────────────────────────────────────

    /** Escenario de "overtime unificado": los 3 flags premium en off. */
    private function unifiedRules(): SurchargeRule
    {
        $rules = clone $this->rules;
        $rules->pay_overtime_dominical = false;
        $rules->pay_overtime_holiday = false;
        $rules->pay_overtime_night = false;

        return $rules;
    }

    public function test_overtime_payable_hours_null_pays_all_worked_hours(): void
    {
        $rules = $this->unifiedRules();

        // 10 horas extra (en varios buckets que colapsan en la bolsa diurna).
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 6.0,
            'overtime_day_dominical_hours' => 2.0,
            'overtime_day_holiday_hours' => 2.0,
        ], $rules, overtimePayableHours: null);

        // 10h × 10000 × 1.25 = 125000
        $this->assertEquals(125000.0, $result['overtime_day']);
        $this->assertEquals(10.0, $result['overtime_worked_hours']);
        $this->assertNull($result['overtime_payable_hours']);
        $this->assertTrue($result['overtime_unified']);
    }

    public function test_overtime_payable_hours_cap_pays_fewer_hours(): void
    {
        $rules = $this->unifiedRules();

        // 10 horas trabajadas, pagar solo 5.
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 10.0,
        ], $rules, overtimePayableHours: 5.0);

        // 5h × 10000 × 1.25 = 62500
        $this->assertEquals(62500.0, $result['overtime_day']);
        $this->assertEquals(10.0, $result['overtime_worked_hours']); // trabajadas no cambian
        $this->assertEquals(5.0, $result['overtime_payable_hours']);
    }

    public function test_overtime_payable_hours_zero_pays_nothing(): void
    {
        $rules = $this->unifiedRules();

        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 10.0,
        ], $rules, overtimePayableHours: 0.0);

        $this->assertEquals(0.0, $result['overtime_day']);
        $this->assertEquals(10.0, $result['overtime_worked_hours']);
        $this->assertEquals(0.0, $result['overtime_payable_hours']);
    }

    public function test_overtime_payable_hours_overpay_exceeds_worked(): void
    {
        $rules = $this->unifiedRules();

        // 10 horas trabajadas, pagar 12 (saldar pendiente de otra quincena).
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 10.0,
        ], $rules, overtimePayableHours: 12.0);

        // 12h × 10000 × 1.25 = 150000
        $this->assertEquals(150000.0, $result['overtime_day']);
        $this->assertEquals(10.0, $result['overtime_worked_hours']);
        $this->assertEquals(12.0, $result['overtime_payable_hours']);
    }

    public function test_overtime_payable_hours_ignored_when_pay_overtime_false(): void
    {
        $rules = $this->unifiedRules();

        // pay_overtime = false → todo compensado; el input no importa.
        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 10.0,
        ], $rules, payOvertime: false, overtimePayableHours: 5.0);

        $this->assertEquals(0.0, $result['overtime_day']);
    }

    public function test_overtime_payable_hours_ignored_when_premium_flags_active(): void
    {
        // Un flag premium ON → overtime no está unificado → input se ignora.
        $rules = clone $this->rules;
        $rules->pay_overtime_dominical = true; // UN flag activo basta
        $rules->pay_overtime_holiday = false;
        $rules->pay_overtime_night = false;

        $result = $this->calculator->execute(10000, [
            'overtime_day_hours' => 10.0,
        ], $rules, overtimePayableHours: 5.0);

        // El input no aplica; paga las 10h trabajadas a tarifa diurna.
        $this->assertEquals(125000.0, $result['overtime_day']);
        $this->assertFalse($result['overtime_unified']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // night_settlement deferred — solo el componente night_surcharge% se difiere
    // ──────────────────────────────────────────────────────────────────────────

    public function test_night_deferral_removes_cutoff_day_surcharge_keeping_base(): void
    {
        // 4h nocturnas en el periodo; de ellas 2h (día de corte) se difieren → la ventana
        // corrida solo tiene 2h. La base de las 2h del corte se queda; su 35% se va.
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, nightWindowHours: ['night_hours' => 2.0]);

        // 2h base+35% (27000) + 2h solo base (20000) = 47000
        $this->assertEquals(47000.0, $result['night']);
        $this->assertEquals(47000.0, $result['total']);
    }

    public function test_night_deferral_adds_surcharge_carried_from_previous_cutoff(): void
    {
        // 4h en el periodo, pero la ventana corrida tiene 6h (2h diferidas del corte anterior).
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, nightWindowHours: ['night_hours' => 6.0]);

        // 54000 (4h×1.35) + 2h×10000×0.35 (7000) = 61000
        $this->assertEquals(61000.0, $result['night']);
    }

    public function test_night_dominical_deferral_keeps_dominical_premium_only_night_defers(): void
    {
        // night_dominical en modo hora = 110% (75 dominical + 35 noche). El día de corte conserva
        // el 75% dominical y solo difiere el 35% nocturno.
        $result = $this->calculator->execute(10000, [
            'night_dominical_hours' => 4.0,
        ], $this->rules, nightWindowHours: ['night_dominical_hours' => 2.0]);

        // 2h corte: base+75% (35000) ; 2h normales: base+110% (42000) = 77000
        $this->assertEquals(77000.0, $result['night_dominical']);
    }

    public function test_night_deferral_in_monthly_defers_only_surcharge(): void
    {
        // En monthly el costo nocturno es solo el recargo (la base va en el salario).
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, salaryType: 'monthly', nightWindowHours: ['night_hours' => 2.0]);

        // Solo 2h pagan su 35%: 2×10000×0.35 = 7000
        $this->assertEquals(7000.0, $result['night']);
    }

    public function test_immediate_mode_null_window_leaves_night_unchanged(): void
    {
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, nightWindowHours: null);

        $this->assertEquals(54000.0, $result['night']); // 4 × 10000 × 1.35
    }

    public function test_collapsed_night_dominical_defers_with_the_night_bucket(): void
    {
        // pay_night_dominical off → night_dominical colapsa en night base; su diferimiento viaja con él.
        $rules = clone $this->rules;
        $rules->pay_night_dominical = false;

        $result = $this->calculator->execute(10000, [
            'night_dominical_hours' => 4.0,
        ], $rules, nightWindowHours: ['night_dominical_hours' => 2.0]);

        // Colapsado a night (35%): 54000 − 2h×0.35×10000 (7000) = 47000 en la línea night; dominical en 0
        $this->assertEquals(47000.0, $result['night']);
        $this->assertEquals(0.0, $result['night_dominical']);
    }

    public function test_monthly_deferral_displays_settled_window_hours_so_hours_times_pct_match(): void
    {
        // Mensual + diferido: la fila nocturna muestra las horas de la ventana liquidada (6h),
        // no las del periodo (4h), de modo que horas × 35% = subtotal.
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, salaryType: 'monthly', nightWindowHours: ['night_hours' => 6.0]);

        $night = collect($result['details'])->firstWhere('type', 'night');

        $this->assertEquals(6.0, $night['hours']);
        $this->assertEquals(21000.0, $result['night']); // 6 × 10000 × 0.35
        $this->assertEquals(round($night['hours'] * 10000 * 0.35, 2), $result['night']);
    }

    public function test_monthly_deferral_folds_collapsed_window_buckets_into_night_hours(): void
    {
        // pay_night_dominical/holiday off → sus horas de ventana se suman a la fila nocturna mostrada.
        $rules = clone $this->rules;
        $rules->pay_night_dominical = false;
        $rules->pay_night_holiday = false;

        $result = $this->calculator->execute(10000, [
            'night_hours' => 3.0,
            'night_dominical_hours' => 1.0,
            'night_holiday_hours' => 0.0,
        ], $rules, salaryType: 'monthly', nightWindowHours: [
            'night_hours' => 3.0,
            'night_dominical_hours' => 1.0,
            'night_holiday_hours' => 2.0,
        ]);

        $night = collect($result['details'])->firstWhere('type', 'night');

        $this->assertEquals(6.0, $night['hours']); // 3 + 1 + 2 (festivo diferido del corte anterior)
        $this->assertEquals(round(6.0 * 10000 * 0.35, 2), $result['night']);
    }

    public function test_hourly_deferral_keeps_period_hours_in_display(): void
    {
        // En hourly la base se paga por fecha del periodo: se conservan las horas del periodo (4h),
        // no las de la ventana (2h).
        $result = $this->calculator->execute(10000, [
            'night_hours' => 4.0,
        ], $this->rules, nightWindowHours: ['night_hours' => 2.0]);

        $night = collect($result['details'])->firstWhere('type', 'night');

        $this->assertEquals(4.0, $night['hours']);
    }
}
