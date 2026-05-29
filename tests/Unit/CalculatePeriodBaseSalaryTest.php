<?php

namespace Tests\Unit;

use App\Domain\TimeTracking\Actions\CalculatePeriodBaseSalary;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CalculatePeriodBaseSalaryTest extends TestCase
{
    private CalculatePeriodBaseSalary $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculatePeriodBaseSalary;
    }

    public function test_full_month_pays_the_whole_salary(): void
    {
        $base = $this->action->execute(2000000, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        $this->assertEquals(2000000.0, $base);
    }

    public function test_first_quincena_pays_half(): void
    {
        $base = $this->action->execute(2000000, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-15'));

        $this->assertEquals(1000000.0, $base);
    }

    public function test_second_quincena_of_31_day_month_pays_half(): void
    {
        // Octubre: 16 al 31 → 15 días comerciales.
        $base = $this->action->execute(2000000, Carbon::parse('2026-10-16'), Carbon::parse('2026-10-31'));

        $this->assertEquals(1000000.0, $base);
    }

    public function test_second_quincena_of_february_pays_half_like_any_month(): void
    {
        // Febrero 2026 (28 días): 16 al 28 → se completa a 15 días comerciales.
        $base = $this->action->execute(2000000, Carbon::parse('2026-02-16'), Carbon::parse('2026-02-28'));

        $this->assertEquals(1000000.0, $base);
    }

    public function test_february_and_october_second_quincena_pay_the_same(): void
    {
        $february = $this->action->execute(2000000, Carbon::parse('2026-02-16'), Carbon::parse('2026-02-28'));
        $october = $this->action->execute(2000000, Carbon::parse('2026-10-16'), Carbon::parse('2026-10-31'));

        $this->assertEquals($february, $october);
    }

    public function test_first_quincena_partial_prorates_by_commercial_days(): void
    {
        // Trabajó del 1 al 8 (8 días comerciales): 2.000.000 × 8/30 = 533.333,33.
        $base = $this->action->execute(2000000, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-08'));

        $this->assertEquals(533333.33, $base);
    }

    public function test_employee_who_left_mid_second_quincena(): void
    {
        // Ingresó/trabajó del 16 al 22 de febrero y se retiró: 7 días comerciales.
        // 2.000.000 × 7/30 = 466.666,67.
        $base = $this->action->execute(2000000, Carbon::parse('2026-02-16'), Carbon::parse('2026-02-22'));

        $this->assertEquals(466666.67, $base);
    }

    public function test_day_31_does_not_add_an_extra_commercial_day(): void
    {
        // Del 30 al 31 de octubre: día 30 y día 31 → ambos topados en 30 → 1 día comercial.
        $base = $this->action->execute(3000000, Carbon::parse('2026-10-30'), Carbon::parse('2026-10-31'));

        // 3.000.000 × 1/30 = 100.000.
        $this->assertEquals(100000.0, $base);
    }

    public function test_two_full_months_pay_two_salaries(): void
    {
        $base = $this->action->execute(1000000, Carbon::parse('2026-03-01'), Carbon::parse('2026-04-30'));

        $this->assertEquals(2000000.0, $base);
    }

    public function test_commercial_days_counts_are_exact(): void
    {
        $this->assertEquals(30, $this->action->commercialDaysBetween(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31')));
        $this->assertEquals(15, $this->action->commercialDaysBetween(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-15')));
        $this->assertEquals(15, $this->action->commercialDaysBetween(Carbon::parse('2026-02-16'), Carbon::parse('2026-02-28')));
        $this->assertEquals(8, $this->action->commercialDaysBetween(Carbon::parse('2026-03-16'), Carbon::parse('2026-03-23')));
    }

    public function test_reversed_range_returns_zero(): void
    {
        $base = $this->action->execute(2000000, Carbon::parse('2026-03-15'), Carbon::parse('2026-03-01'));

        $this->assertEquals(0.0, $base);
    }

    /**
     * Regresión: la app usa Date::use(CarbonImmutable), donde addMonth() NO muta.
     * El loop debe avanzar igual (no colgarse) y dar el resultado correcto.
     */
    public function test_works_with_carbon_immutable_dates(): void
    {
        $fullMonth = $this->action->execute(2000000, CarbonImmutable::parse('2026-05-01'), CarbonImmutable::parse('2026-05-31'));
        $this->assertEquals(2000000.0, $fullMonth);

        $secondQuincena = $this->action->execute(2000000, CarbonImmutable::parse('2026-05-16'), CarbonImmutable::parse('2026-05-31'));
        $this->assertEquals(1000000.0, $secondQuincena);

        // Caso real del controller: start === end aliased a fin de mes (now()->endOfMonth()).
        $aliased = CarbonImmutable::parse('2026-05-31 23:59:59');
        $this->assertEquals(66666.67, $this->action->execute(2000000, $aliased, $aliased));
    }
}
