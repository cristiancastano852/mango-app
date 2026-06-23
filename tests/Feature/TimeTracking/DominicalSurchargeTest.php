<?php

namespace Tests\Feature\TimeTracking;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\DominicalPaymentDecision;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DominicalSurchargeTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create(['name' => 'Test Co', 'slug' => 'test-co']);

        $this->adminUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->adminUser->assignRole('admin');

        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
            'salary_type' => 'hourly',
            'dominical_payment_mode' => 'day',
            'dominical_day_value' => 60000,
        ]);
    }

    /**
     * Crea un turno dominical ya clasificado con las horas indicadas en dominical_hours.
     */
    private function dominicalEntry(string $date, float $dominicalHours = 6.0): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => $date.' 08:00:00',
            'clock_out' => $date.' 14:00:00',
            'gross_hours' => $dominicalHours,
            'break_hours' => 0,
            'net_hours' => $dominicalHours,
            'dominical_hours' => $dominicalHours,
            'status' => 'calculated',
            'pin_verified' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function period(): array
    {
        // 2026-03-01, 08 y 15 son domingos → 3 dominicales en el periodo.
        return ['date_range' => 'custom', 'start_date' => '2026-03-01', 'end_date' => '2026-03-15'];
    }

    public function test_day_mode_pays_all_dominicals_by_default(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');
        $this->dominicalEntry('2026-03-15');

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            ...$this->period(),
            'employee_id' => $this->employee->id,
        ]));

        $response->assertOk();
        // base 18h × 10000 = 180000 + plus 3 × 60000 = 180000 → 360000
        $response->assertInertia(fn ($page) => $page
            ->where('report.cost_summary.dominical', 360000)
            ->where('report.cost_summary.dominical_worked_days', 3)
            ->where('report.cost_summary.dominical_paid_days', 3)
        );

        // Ver el reporte no persiste ninguna decisión.
        $this->assertDatabaseMissing('dominical_payment_decisions', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_request_count_reduces_premiums_without_persisting_on_view(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');
        $this->dominicalEntry('2026-03-15');

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            ...$this->period(),
            'employee_id' => $this->employee->id,
            'dominical_payable_count' => 1,
        ]));

        $response->assertOk();
        // base 180000 + plus 1 × 60000 = 240000
        $response->assertInertia(fn ($page) => $page
            ->where('report.cost_summary.dominical', 240000)
            ->where('report.cost_summary.dominical_paid_days', 1)
            ->where('filters.dominical_payable_count', 1)
        );

        $this->assertDatabaseMissing('dominical_payment_decisions', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_exporting_employee_report_persists_decision(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');
        $this->dominicalEntry('2026-03-15');

        $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [
            ...$this->period(),
            'employee_id' => $this->employee->id,
            'dominical_payable_count' => 2,
        ]))->assertOk();

        $this->assertDatabaseHas('dominical_payment_decisions', [
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-15',
            'payable_count' => 2,
            'exported_by' => $this->adminUser->id,
        ]);
    }

    public function test_reexport_overwrites_decision_keeping_one_row(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');

        $params = [...$this->period(), 'employee_id' => $this->employee->id];

        $this->actingAs($this->adminUser)->get(route('reports.employee.excel', [...$params, 'dominical_payable_count' => 2]))->assertOk();
        $this->actingAs($this->adminUser)->get(route('reports.employee.pdf', [...$params, 'dominical_payable_count' => 0]))->assertOk();

        $this->assertDatabaseCount('dominical_payment_decisions', 1);
        $this->assertDatabaseHas('dominical_payment_decisions', [
            'employee_id' => $this->employee->id,
            'payable_count' => 0,
        ]);
    }

    public function test_saved_decision_preloads_in_report_view(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');
        $this->dominicalEntry('2026-03-15');

        DominicalPaymentDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-15',
            'payable_count' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            ...$this->period(),
            'employee_id' => $this->employee->id,
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('filters.dominical_payable_count', 1)
            ->where('report.cost_summary.dominical_paid_days', 1)
        );
    }

    public function test_company_report_does_not_persist_dominical_decision(): void
    {
        $this->dominicalEntry('2026-03-01');

        $this->actingAs($this->adminUser)->get(route('reports.company.excel', $this->period()))->assertOk();

        $this->assertDatabaseMissing('dominical_payment_decisions', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_company_report_respects_saved_employee_decision(): void
    {
        $this->dominicalEntry('2026-03-01');
        $this->dominicalEntry('2026-03-08');
        $this->dominicalEntry('2026-03-15');

        DominicalPaymentDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-15',
            'payable_count' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.company', $this->period()));

        // base 180000 + 1 plus de 60000 = 240000 (respeta la decisión guardada del empleado)
        $response->assertInertia(fn ($page) => $page
            ->where('report.cost_summary.dominical', 240000)
        );
    }

    public function test_holiday_is_always_paid_even_when_dominical_disabled(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_dominical_by_default' => false, 'sunday_holiday' => 75]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:00:00',
            'clock_out' => '2026-03-10 14:00:00',
            'gross_hours' => 6,
            'break_hours' => 0,
            'net_hours' => 6,
            'holiday_hours' => 6,
            'status' => 'calculated',
            'pin_verified' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('reports.employee', [
            'date_range' => 'custom', 'start_date' => '2026-03-10', 'end_date' => '2026-03-10',
            'employee_id' => $this->employee->id,
        ]));

        // Festivo siempre paga: 6 × 10000 × 1.75 = 105000
        $response->assertInertia(fn ($page) => $page
            ->where('report.cost_summary.holiday', 105000)
        );
    }
}
