<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\TimeTracking\Actions\GenerateCompanyReport;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateCompanyReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee1;

    private Employee $employee2;

    private Department $deptA;

    private Department $deptB;

    private GenerateCompanyReport $action;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-co',
        ]);

        // CompanyObserver ya crea SurchargeRule automáticamente

        $this->deptA = Department::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Cocina',
        ]);

        $this->deptB = Department::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Servicio',
        ]);

        $user1 = User::factory()->create(['company_id' => $this->company->id]);
        $user1->assignRole('employee');
        $this->employee1 = Employee::create([
            'user_id' => $user1->id,
            'company_id' => $this->company->id,
            'department_id' => $this->deptA->id,
            'hourly_rate' => 10000,
        ]);

        $user2 = User::factory()->create(['company_id' => $this->company->id]);
        $user2->assignRole('employee');
        $this->employee2 = Employee::create([
            'user_id' => $user2->id,
            'company_id' => $this->company->id,
            'department_id' => $this->deptB->id,
            'hourly_rate' => 15000,
        ]);

        $this->action = app(GenerateCompanyReport::class);
    }

    public function test_aggregates_across_all_employees(): void
    {
        $this->createEntry($this->employee1, '2026-03-05', 8.0, 7.0, 1.0);
        $this->createEntry($this->employee2, '2026-03-05', 8.0, 6.0, 2.0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(2, $result['totals']['total_employees']);
        $this->assertEquals(2, $result['totals']['total_days_worked']);
        $this->assertEquals(16.0, $result['totals']['net_hours']);
        $this->assertEquals(13.0, $result['totals']['regular_hours']);
        $this->assertEquals(3.0, $result['totals']['night_hours']);
    }

    public function test_filters_by_department(): void
    {
        $this->createEntry($this->employee1, '2026-03-05', 8.0, 8.0, 0);
        $this->createEntry($this->employee2, '2026-03-05', 8.0, 8.0, 0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
            $this->deptA->id,
        );

        $this->assertEquals(1, $result['totals']['total_employees']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);
    }

    public function test_per_employee_breakdown_includes_cost(): void
    {
        $this->createEntry($this->employee1, '2026-03-05', 8.0, 8.0, 0);
        $this->createEntry($this->employee2, '2026-03-05', 8.0, 8.0, 0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertCount(2, $result['employees']);

        // Empleados están ordenados por net_hours DESC (iguales aquí)
        foreach ($result['employees'] as $emp) {
            $this->assertEquals(8.0, $emp['net_hours']);
            $this->assertArrayHasKey('cost', $emp);
            $this->assertGreaterThan(0, $emp['cost']);
        }
    }

    public function test_company_total_cost_sums_employee_costs(): void
    {
        // emp1: 8h regular × $10,000 = $80,000
        $this->createEntry($this->employee1, '2026-03-05', 8.0, 8.0, 0);
        // emp2: 8h regular × $15,000 = $120,000
        $this->createEntry($this->employee2, '2026-03-05', 8.0, 8.0, 0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        // Total: $80,000 + $120,000 = $200,000
        $this->assertEquals(200000.0, $result['cost_summary']['total']);
        $this->assertEquals(200000.0, $result['cost_summary']['regular']);
    }

    public function test_daily_attendance_trend(): void
    {
        $this->createEntry($this->employee1, '2026-03-05', 8.0, 8.0, 0);
        $this->createEntry($this->employee2, '2026-03-05', 8.0, 8.0, 0);
        $this->createEntry($this->employee1, '2026-03-06', 8.0, 8.0, 0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertCount(2, $result['daily_attendance']);
        // 5 de marzo: 2 empleados
        $day5 = collect($result['daily_attendance'])->firstWhere('date', '2026-03-05');
        $this->assertEquals(2, $day5['employees_present']);
        // 6 de marzo: 1 empleado
        $day6 = collect($result['daily_attendance'])->firstWhere('date', '2026-03-06');
        $this->assertEquals(1, $day6['employees_present']);
    }

    public function test_no_data_returns_empty_totals(): void
    {
        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertEquals(0, $result['totals']['total_employees']);
        $this->assertEquals(0.0, $result['totals']['net_hours']);
        $this->assertEquals(0.0, $result['cost_summary']['total']);
        $this->assertEmpty($result['employees']);
        $this->assertEmpty($result['daily_attendance']);
    }

    public function test_excludes_other_company_data(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'slug' => 'other-co']);
        // CompanyObserver ya crea SurchargeRule automáticamente

        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'hourly_rate' => 20000,
        ]);

        $this->createEntry($this->employee1, '2026-03-05', 8.0, 8.0, 0);
        $this->createEntryForEmployee($otherEmployee, $otherCompany->id, '2026-03-05', 8.0, 8.0, 0);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(1, $result['totals']['total_employees']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);
    }

    public function test_night_and_overtime_costs_calculated_correctly(): void
    {
        // emp1: 4h regular + 2h night + 2h overtime = 8h
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee1->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 4.0,
            'night_hours' => 2.0,
            'overtime_hours' => 2.0,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->company->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        // regular: 4 × 10000 = 40000
        // night: 2 × 10000 × 1.35 = 27000
        // overtime: 2 × 10000 × 1.25 = 25000
        $this->assertEquals(40000.0, $result['cost_summary']['regular']);
        $this->assertEquals(27000.0, $result['cost_summary']['night']);
        $this->assertEquals(25000.0, $result['cost_summary']['overtime']);
        $this->assertEquals(92000.0, $result['cost_summary']['total']);
    }

    private function createEntry(Employee $employee, string $date, float $netHours, float $regularHours, float $nightHours): void
    {
        $this->createEntryForEmployee($employee, $this->company->id, $date, $netHours, $regularHours, $nightHours);
    }

    private function createEntryForEmployee(Employee $employee, int $companyId, string $date, float $netHours, float $regularHours, float $nightHours): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $companyId,
            'date' => $date,
            'clock_in' => "{$date} 08:00:00",
            'clock_out' => "{$date} 17:00:00",
            'gross_hours' => $netHours + 1,
            'break_hours' => 1.0,
            'net_hours' => $netHours,
            'regular_hours' => $regularHours,
            'night_hours' => $nightHours,
            'overtime_hours' => 0,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);
    }
}
