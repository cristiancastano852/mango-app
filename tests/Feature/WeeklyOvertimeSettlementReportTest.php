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

    private function makeEntry(string $date, float $regular, float $overtimeDay): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => "$date 08:00:00",
            'clock_out' => "$date 18:00:00",
            'gross_hours' => $regular + $overtimeDay,
            'break_hours' => 0,
            'net_hours' => $regular + $overtimeDay,
            'regular_hours' => $regular,
            'overtime_day_hours' => $overtimeDay,
            'status' => 'calculated',
        ]);
    }

    public function test_period_closing_midweek_excludes_overtime_of_the_open_week(): void
    {
        $this->setMode('weekly');
        // Jun 14 = domingo, Jun 15 = lunes (semana en curso al cierre).
        $this->makeEntry('2026-06-08', 6.0, 2.0); // semana cierra dom 14 → liquida en Q1
        $this->makeEntry('2026-06-15', 5.0, 3.0); // semana cierra dom 21 → se difiere

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        // El overtime de la semana en curso (Jun 15) se difiere.
        $this->assertEqualsWithDelta(2.0, $result['totals']['overtime_day_hours'], 0.01);
        // Las horas base se cuentan por fecha en el periodo (no se difieren).
        $this->assertEqualsWithDelta(11.0, $result['totals']['regular_hours'], 0.01);

        $this->assertSame('weekly', $result['overtime_settlement']['mode']);
        $this->assertSame('2026-06-14', $result['overtime_settlement']['end']);
        $this->assertTrue($result['overtime_settlement']['deferred']);
    }

    public function test_next_period_settles_the_deferred_overtime(): void
    {
        $this->setMode('weekly');
        $this->makeEntry('2026-06-15', 5.0, 3.0); // diferida desde Q1; domingo 21 ∈ Q2

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-16'),
            Carbon::parse('2026-06-30'),
        );

        // El overtime del lunes 15 se liquida en Q2 (la ventana arranca el lunes 15).
        $this->assertEqualsWithDelta(3.0, $result['totals']['overtime_day_hours'], 0.01);
        // Su base ya se pagó en Q1 (Jun 15 está fuera del rango [16,30]).
        $this->assertEqualsWithDelta(0.0, $result['totals']['regular_hours'], 0.01);
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
    }
}
