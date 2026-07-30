<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WeeklyOvertimeSettlementReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private GenerateEmployeeReport $action;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create(['name' => 'Weekly Co', 'slug' => 'weekly-co']);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);

        $this->action = app(GenerateEmployeeReport::class);
    }

    private function setMode(string $mode): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['overtime_accrual_mode' => $mode]);
    }

    private function makeEntry(string $date, float $regular, float $overtimeDay, float $overtimeNight = 0.0): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => "$date 08:00:00",
            'clock_out' => "$date 18:00:00",
            'gross_hours' => $regular + $overtimeDay + $overtimeNight,
            'break_hours' => 0,
            'net_hours' => $regular + $overtimeDay + $overtimeNight,
            'regular_hours' => $regular,
            'overtime_day_hours' => $overtimeDay,
            'overtime_night_hours' => $overtimeNight,
            'status' => 'calculated',
        ]);
    }

    public function test_period_closing_midweek_excludes_overtime_of_the_open_week(): void
    {
        $this->setMode('weekly');
        // Jun 14 = domingo, Jun 15 = lunes (semana en curso al cierre).
        $this->makeEntry('2026-06-01', 42.0, 0.0);
        $this->makeEntry('2026-06-08', 42.0, 2.0); // semana cierra dom 14 → liquida en Q1
        $this->makeEntry('2026-06-15', 5.0, 3.0); // semana cierra dom 21 → se difiere

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        // El overtime de la semana en curso (Jun 15) se difiere.
        $this->assertEqualsWithDelta(2.0, $result['totals']['overtime_day_hours'], 0.01);
        // Las horas base se cuentan por fecha en el periodo (no se difieren).
        $this->assertEqualsWithDelta(89.0, $result['totals']['regular_hours'], 0.01);

        $this->assertSame('weekly', $result['overtime_settlement']['mode']);
        $this->assertSame('2026-06-14', $result['overtime_settlement']['end']);
        $this->assertTrue($result['overtime_settlement']['deferred']);
        $this->assertSame(120, $result['overtime_settlement']['payable_overtime_minutes']);
    }

    public function test_next_period_settles_the_deferred_overtime(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-06-15', 42.0, 3.0); // diferida desde Q1; domingo 21 ∈ Q2
        $this->makeEntry('2026-06-22', 42.0, 0.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-16'),
            Carbon::parse('2026-06-30'),
        );

        // El overtime del lunes 15 se liquida en Q2 (la ventana arranca el lunes 15).
        $this->assertEqualsWithDelta(3.0, $result['totals']['overtime_day_hours'], 0.01);
        // Su base ya se pagó en Q1 (Jun 15 está fuera del rango [16,30]).
        $this->assertEqualsWithDelta(42.0, $result['totals']['regular_hours'], 0.01);
        $this->assertSame('2026-06-15', $result['overtime_settlement']['start']);
    }

    public function test_period_without_any_sunday_defers_all_overtime(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-06-03', 6.0, 2.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-05'),
        );

        $this->assertEqualsWithDelta(0.0, $result['totals']['overtime_day_hours'], 0.01);
        $this->assertEqualsWithDelta(6.0, $result['totals']['regular_hours'], 0.01);
    }

    public function test_daily_breakdown_marks_deferred_days(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-06-08', 6.0, 2.0);
        $this->makeEntry('2026-06-15', 5.0, 3.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        $days = collect($result['daily_breakdown'])->keyBy('date');
        $this->assertFalse($days['2026-06-08']['overtime_deferred']);
        $this->assertTrue($days['2026-06-15']['overtime_deferred']);
    }

    public function test_daily_mode_sums_overtime_over_the_whole_period(): void
    {
        $this->setMode('daily');
        $this->makeEntry('2026-06-08', 6.0, 2.0);
        $this->makeEntry('2026-06-15', 5.0, 3.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        // Modo diario: el overtime se suma por rango del periodo, sin ventana semanal.
        $this->assertEqualsWithDelta(5.0, $result['totals']['overtime_day_hours'], 0.01);
        $this->assertFalse($result['overtime_settlement']['deferred']);
        $this->assertSame(300, $result['overtime_settlement']['payable_overtime_minutes']);
    }

    public function test_weekly_report_nets_deficit_against_overtime(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-07-13', 41.60, 0.0);
        $this->makeEntry('2026-07-20', 42.0, 1.05);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertEqualsWithDelta(0.65, $result['totals']['overtime_day_hours'], 0.01);
        $this->assertSame(63, $result['overtime_settlement']['worked_overtime_minutes']);
        $this->assertSame(24, $result['overtime_settlement']['offset_minutes']);
        $this->assertSame(39, $result['overtime_settlement']['payable_overtime_minutes']);
        $this->assertSame(0, $result['overtime_settlement']['deficit_minutes']);
        $this->assertEqualsWithDelta(8125.0, $result['cost_summary']['overtime_day'], 0.01);
    }

    public function test_weekly_report_normalizes_stale_buckets_to_the_payable_balance(): void
    {
        $this->setMode('weekly');
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update([
                'pay_overtime_dominical' => false,
                'pay_overtime_holiday' => false,
                'pay_overtime_night' => false,
            ]);

        $this->makeEntry('2026-07-13', 39.12, 0.0);
        $this->makeEntry('2026-07-19', 7.49, 0.0, 0.02);
        $this->makeEntry('2026-07-20', 41.65, 0.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
            payOvertime: false,
        );

        $overtimeDay = collect($result['cost_summary']['details'])->firstWhere('type', 'overtime_day');

        $this->assertSame(278, $result['overtime_settlement']['worked_overtime_minutes']);
        $this->assertSame(21, $result['overtime_settlement']['offset_minutes']);
        $this->assertSame(257, $result['overtime_settlement']['payable_overtime_minutes']);
        $this->assertEqualsWithDelta(257 / 60, $overtimeDay['hours'], 0.01);
        $this->assertTrue($overtimeDay['compensated']);
        $this->assertSame(0.0, $overtimeDay['subtotal']);
    }

    public function test_weekly_report_uses_day_overtime_when_no_bucket_was_classified(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-07-13', 46.63, 0.0);
        $this->makeEntry('2026-07-20', 41.65, 0.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(278, $result['overtime_settlement']['worked_overtime_minutes']);
        $this->assertSame(257, $result['overtime_settlement']['payable_overtime_minutes']);
        $this->assertEqualsWithDelta(257 / 60, $result['totals']['overtime_day_hours'], 0.01);
    }

    public function test_weekly_deficit_is_informational_and_does_not_reduce_cost(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-07-13', 40.0, 0.0);
        $this->makeEntry('2026-07-20', 41.0, 0.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(180, $result['overtime_settlement']['deficit_minutes']);
        $this->assertSame(0, $result['overtime_settlement']['payable_overtime_minutes']);
        $this->assertEqualsWithDelta(410000.0, $result['cost_summary']['total'], 0.01);
    }

    public function test_weekly_balance_scales_multiple_overtime_categories_proportionally(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-07-13', 41.60, 0.0);
        $this->makeEntry('2026-07-20', 42.0, 0.35, 0.70);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
        );

        $factor = 39 / 63;
        $this->assertEqualsWithDelta(0.35 * $factor, $result['totals']['overtime_day_hours'], 0.01);
        $this->assertEqualsWithDelta(0.70 * $factor, $result['totals']['overtime_night_hours'], 0.01);
        $this->assertSame(39, $result['overtime_settlement']['payable_overtime_minutes']);
    }

    public function test_weekly_deficit_does_not_reduce_monthly_base_salary(): void
    {
        $this->setMode('weekly');
        $this->employee->update([
            'salary_type' => 'monthly',
            'monthly_base_salary' => 2000000,
            'receives_transport_allowance' => false,
        ]);
        $this->makeEntry('2026-07-13', 40.0, 0.0);
        $this->makeEntry('2026-07-20', 41.0, 0.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertSame(180, $result['overtime_settlement']['deficit_minutes']);
        $this->assertEqualsWithDelta(1000000.0, $result['cost_summary']['base'], 0.01);
        $this->assertEqualsWithDelta(1000000.0, $result['cost_summary']['total'], 0.01);
    }
}
