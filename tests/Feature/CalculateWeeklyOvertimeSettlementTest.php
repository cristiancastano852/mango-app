<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWeeklyOvertimeSettlement;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalculateWeeklyOvertimeSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private CalculateWeeklyOvertimeSettlement $action;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);
        $this->company = Company::create(['name' => 'Weekly Co', 'slug' => 'weekly-balance']);
        $this->employee = $this->createEmployee($this->company);
        $this->action = app(CalculateWeeklyOvertimeSettlement::class);
    }

    public function test_offsets_weekly_deficit_against_overtime(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 41.60);
        $this->createEntry($this->employee, '2026-07-20', 43.05, 1.05);

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertSame(-24, $result['weeks'][0]['balance_minutes']);
        $this->assertSame(63, $result['weeks'][1]['balance_minutes']);
        $this->assertSame(39, $result['balance_minutes']);
        $this->assertSame(63, $result['worked_overtime_minutes']);
        $this->assertSame(24, $result['offset_minutes']);
        $this->assertSame(39, $result['payable_overtime_minutes']);
        $this->assertSame(0, $result['deficit_minutes']);
        $this->assertEqualsWithDelta(39 / 63, $result['payable_factor'], 0.0001);
    }

    public function test_uses_weekly_excess_when_persisted_overtime_buckets_are_stale(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 46.63, 0.02);
        $this->createEntry($this->employee, '2026-07-20', 41.65);

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertSame(278, $result['weeks'][0]['balance_minutes']);
        $this->assertSame(-21, $result['weeks'][1]['balance_minutes']);
        $this->assertSame(278, $result['worked_overtime_minutes']);
        $this->assertSame(21, $result['offset_minutes']);
        $this->assertSame(257, $result['payable_overtime_minutes']);
        $this->assertEqualsWithDelta(257 / 278, $result['payable_factor'], 0.0001);
    }

    public function test_reports_combined_deficit_without_payable_overtime(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 40.0);
        $this->createEntry($this->employee, '2026-07-20', 41.0);

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertSame(-180, $result['balance_minutes']);
        $this->assertSame(180, $result['deficit_minutes']);
        $this->assertSame(0, $result['payable_overtime_minutes']);
        $this->assertSame(0, $result['offset_minutes']);
    }

    public function test_preserves_overtime_when_all_weeks_exceed_the_limit(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 43.0, 1.0);
        $this->createEntry($this->employee, '2026-07-20', 44.0, 2.0);

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertSame(180, $result['balance_minutes']);
        $this->assertSame(180, $result['worked_overtime_minutes']);
        $this->assertSame(180, $result['payable_overtime_minutes']);
        $this->assertSame(0, $result['offset_minutes']);
        $this->assertSame(0, $result['deficit_minutes']);
        $this->assertSame(1.0, $result['payable_factor']);
    }

    public function test_returns_zero_balance_for_an_empty_window(): void
    {
        $result = $this->action->execute(
            $this->company->id,
            [$this->employee->id],
            ['start' => null, 'end' => null, 'deferred' => true],
            2520,
        )[$this->employee->id];

        $this->assertSame(0, $result['balance_minutes']);
        $this->assertSame(0, $result['worked_overtime_minutes']);
        $this->assertSame([], $result['weeks']);
    }

    public function test_exact_period_balance_offsets_all_worked_overtime(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 40.0);
        $this->createEntry($this->employee, '2026-07-20', 44.0, 2.0);

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertSame(0, $result['balance_minutes']);
        $this->assertSame(120, $result['worked_overtime_minutes']);
        $this->assertSame(120, $result['offset_minutes']);
        $this->assertSame(0, $result['payable_overtime_minutes']);
        $this->assertSame(0, $result['deficit_minutes']);
    }

    public function test_ignores_open_and_soft_deleted_entries(): void
    {
        $this->createEntry($this->employee, '2026-07-13', 42.0);
        $this->createEntry($this->employee, '2026-07-20', 42.0);

        $open = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-07-14',
            'clock_in' => '2026-07-14 08:00:00',
            'clock_out' => null,
            'gross_hours' => 0,
            'net_hours' => 10.0,
            'overtime_day_hours' => 10.0,
            'status' => 'pending',
        ]);
        $deleted = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-07-21',
            'clock_in' => '2026-07-21 08:00:00',
            'clock_out' => '2026-07-21 18:00:00',
            'gross_hours' => 10.0,
            'net_hours' => 10.0,
            'overtime_day_hours' => 10.0,
            'status' => 'calculated',
        ]);
        $deleted->delete();

        $result = $this->settle([$this->employee->id])[$this->employee->id];

        $this->assertNull($open->clock_out);
        $this->assertSame(0, $result['balance_minutes']);
        $this->assertSame(0, $result['worked_overtime_minutes']);
    }

    public function test_keeps_employees_and_companies_isolated(): void
    {
        $secondEmployee = $this->createEmployee($this->company);
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-weekly-balance']);
        $otherEmployee = $this->createEmployee($otherCompany);

        $this->createEntry($this->employee, '2026-07-13', 43.0, 1.0);
        $this->createEntry($this->employee, '2026-07-20', 42.0);
        $this->createEntry($secondEmployee, '2026-07-13', 40.0);
        $this->createEntry($secondEmployee, '2026-07-20', 42.0);
        $this->createEntry($otherEmployee, '2026-07-13', 100.0, 58.0);

        $result = $this->settle([$this->employee->id, $secondEmployee->id, $otherEmployee->id]);

        $this->assertSame(60, $result[$this->employee->id]['payable_overtime_minutes']);
        $this->assertSame(120, $result[$secondEmployee->id]['deficit_minutes']);
        $this->assertSame(5040, $result[$otherEmployee->id]['deficit_minutes']);
        $this->assertSame(0, $result[$otherEmployee->id]['worked_overtime_minutes']);
    }

    /**
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<string, mixed>>
     */
    private function settle(array $employeeIds): array
    {
        return $this->action->execute(
            $this->company->id,
            $employeeIds,
            ['start' => '2026-07-13', 'end' => '2026-07-26', 'deferred' => false],
            2520,
        );
    }

    private function createEmployee(Company $company): Employee
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('employee');

        return Employee::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'hourly_rate' => 10000,
        ]);
    }

    private function createEntry(Employee $employee, string $date, float $netHours, float $overtimeHours = 0.0): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'date' => $date,
            'clock_in' => "$date 00:00:00",
            'clock_out' => "$date 23:59:00",
            'gross_hours' => $netHours,
            'break_hours' => 0,
            'net_hours' => $netHours,
            'regular_hours' => max(0, $netHours - $overtimeHours),
            'overtime_day_hours' => $overtimeHours,
            'status' => 'calculated',
        ]);
    }
}
