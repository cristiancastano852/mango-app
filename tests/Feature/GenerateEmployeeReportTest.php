<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\GenerateEmployeeReport;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateEmployeeReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private GenerateEmployeeReport $action;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-co',
        ]);

        // CompanyObserver ya crea SurchargeRule automáticamente

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);

        $this->action = app(GenerateEmployeeReport::class);
    }

    public function test_aggregates_hours_correctly_for_date_range(): void
    {
        // Crear 3 días de trabajo
        foreach ([1, 2, 3] as $day) {
            TimeEntry::withoutGlobalScopes()->create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => "2026-03-0{$day}",
                'clock_in' => "2026-03-0{$day} 08:00:00",
                'clock_out' => "2026-03-0{$day} 17:00:00",
                'gross_hours' => 9.0,
                'break_hours' => 1.0,
                'net_hours' => 8.0,
                'regular_hours' => 7.0,
                'night_hours' => 1.0,
                'overtime_day_hours' => 0,
                'sunday_holiday_hours' => 0,
                'status' => 'calculated',
            ]);
        }

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-03'),
        );

        $this->assertEquals(3, $result['totals']['days_worked']);
        $this->assertEquals(27.0, $result['totals']['gross_hours']);
        $this->assertEquals(24.0, $result['totals']['net_hours']);
        $this->assertEquals(21.0, $result['totals']['regular_hours']);
        $this->assertEquals(3.0, $result['totals']['night_hours']);
    }

    public function test_excludes_entries_outside_date_range(): void
    {
        // Entry dentro del rango
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        // Entry fuera del rango
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-10',
            'clock_in' => '2026-03-10 08:00:00',
            'clock_out' => '2026-03-10 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(1, $result['totals']['days_worked']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);
    }

    public function test_excludes_entries_without_clock_out(): void
    {
        // Entry completa
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 0,
            'net_hours' => 9.0,
            'regular_hours' => 9.0,
            'status' => 'calculated',
        ]);

        // Entry en progreso (sin clock_out)
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-06',
            'clock_in' => '2026-03-06 08:00:00',
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(1, $result['totals']['days_worked']);
    }

    public function test_handles_employee_with_no_entries(): void
    {
        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertEquals(0, $result['totals']['days_worked']);
        $this->assertEquals(0.0, $result['totals']['net_hours']);
        $this->assertEquals(0.0, $result['cost_summary']['total']);
        $this->assertEmpty($result['breaks_by_type']);
        $this->assertEmpty($result['daily_breakdown']);
    }

    public function test_breaks_grouped_by_type(): void
    {
        $lunchType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'icon' => '🍽️',
            'color' => '#FF9800',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $breakType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Descanso',
            'slug' => 'descanso',
            'icon' => '☕',
            'color' => '#4CAF50',
            'is_paid' => true,
            'is_active' => true,
        ]);

        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        // 2 almuerzos de 30 min y 1 descanso de 15 min
        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $lunchType->id,
            'started_at' => '2026-03-05 12:00:00',
            'ended_at' => '2026-03-05 12:30:00',
            'duration_minutes' => 30,
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $lunchType->id,
            'started_at' => '2026-03-05 13:00:00',
            'ended_at' => '2026-03-05 13:30:00',
            'duration_minutes' => 30,
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $breakType->id,
            'started_at' => '2026-03-05 15:00:00',
            'ended_at' => '2026-03-05 15:15:00',
            'duration_minutes' => 15,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertCount(2, $result['breaks_by_type']);

        // Almuerzo tiene más minutos, debe estar primero (ordenado por total_minutes DESC)
        $lunch = $result['breaks_by_type'][0];
        $this->assertEquals('Almuerzo', $lunch['name']);
        $this->assertEquals(60, $lunch['total_minutes']);
        $this->assertEquals(2, $lunch['count']);
        $this->assertFalse($lunch['is_paid']);

        $break = $result['breaks_by_type'][1];
        $this->assertEquals('Descanso', $break['name']);
        $this->assertEquals(15, $break['total_minutes']);
        $this->assertEquals(1, $break['count']);
        $this->assertTrue($break['is_paid']);
    }

    public function test_daily_breakdown_returns_data_per_day(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-02',
            'clock_in' => '2026-03-02 08:00:00',
            'clock_out' => '2026-03-02 16:00:00',
            'gross_hours' => 8.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-04',
            'clock_in' => '2026-03-04 08:00:00',
            'clock_out' => '2026-03-04 18:00:00',
            'gross_hours' => 10.0,
            'break_hours' => 1.0,
            'net_hours' => 9.0,
            'regular_hours' => 9.0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertCount(2, $result['daily_breakdown']);
        // Debe estar ordenado cronológicamente
        $this->assertStringContainsString('2026-03-02', $result['daily_breakdown'][0]['date']);
        $this->assertStringContainsString('2026-03-04', $result['daily_breakdown'][1]['date']);
        $this->assertEquals(8.0, $result['daily_breakdown'][0]['net_hours']);
        $this->assertEquals(9.0, $result['daily_breakdown'][1]['net_hours']);
    }

    public function test_cost_calculation_uses_employee_hourly_rate(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 6.0,
            'night_hours' => 2.0,
            'overtime_day_hours' => 0,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        // Regular: 6h × $10,000 = $60,000
        $this->assertEquals(60000.0, $result['cost_summary']['regular']);
        // Night: 2h × $10,000 × 1.35 = $27,000
        $this->assertEquals(27000.0, $result['cost_summary']['night']);
        $this->assertEquals(87000.0, $result['cost_summary']['total']);
    }

    public function test_employee_info_is_included_in_report(): void
    {
        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals($this->employee->id, $result['employee']['id']);
        $this->assertNotEmpty($result['employee']['name']);
        $this->assertEquals(10000.0, $result['employee']['hourly_rate']);
    }

    public function test_period_dates_are_returned(): void
    {
        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertEquals('2026-03-01', $result['period']['start']);
        $this->assertEquals('2026-03-15', $result['period']['end']);
    }

    // ----------------------------------------------------------------------------------
    // Modo salario mensual (monthly): salario base fijo por quincena + recargos/extras.
    // Empleado de referencia: base $2.000.000/mes, valor hora $8.000.
    // ----------------------------------------------------------------------------------

    public function test_monthly_full_first_quincena_ordinary_pays_only_base(): void
    {
        $employee = $this->makeMonthlyEmployee(2000000, 8000);

        // Jornada ordinaria pura repartida en varios días (8h diurnas c/u).
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06'] as $date) {
            $this->createMonthlyEntry($employee, $date, regular: 8.0);
        }

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        // Primera quincena completa → base = 2.000.000 / 2 = 1.000.000.
        $this->assertEquals('monthly', $result['employee']['salary_type']);
        $this->assertEquals(2000000.0, $result['employee']['monthly_base_salary']);
        $this->assertEquals(1000000.0, $result['cost_summary']['base']);
        $this->assertEquals(0.0, $result['cost_summary']['regular']);
        // Solo trabajó ordinario → total es exactamente el salario base de la quincena.
        $this->assertEquals(1000000.0, $result['cost_summary']['total']);
    }

    public function test_monthly_quincena_with_night_and_overtime(): void
    {
        $employee = $this->makeMonthlyEmployee(2000000, 8000);

        // Día con 10 horas nocturnas dentro de la jornada.
        $this->createMonthlyEntry($employee, '2026-03-03', regular: 0.0, night: 10.0);
        // Día con 5 horas extra diurnas.
        $this->createMonthlyEntry($employee, '2026-03-04', regular: 8.0, overtimeDay: 5.0);

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        // base 1.000.000
        // nocturno (solo 35%): 10 × 8000 × 0.35 = 28.000
        // extra diurna (completa): 5 × 8000 × 1.25 = 50.000
        $this->assertEquals(1000000.0, $result['cost_summary']['base']);
        $this->assertEquals(28000.0, $result['cost_summary']['night']);
        $this->assertEquals(50000.0, $result['cost_summary']['overtime_day']);
        $this->assertEquals(1000000.0 + 28000.0 + 50000.0, $result['cost_summary']['total']);
    }

    public function test_monthly_february_and_october_full_quincena_pay_the_same_base(): void
    {
        $employee = $this->makeMonthlyEmployee(2000000, 8000);

        // Febrero: segunda quincena (16–28), trabajó menos días calendario.
        $this->createMonthlyEntry($employee, '2026-02-16', regular: 8.0);
        $this->createMonthlyEntry($employee, '2026-02-27', regular: 8.0);

        $february = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-02-16'),
            Carbon::parse('2026-02-28'),
        );

        // Octubre: segunda quincena (16–31), trabajó más días calendario.
        $this->createMonthlyEntry($employee, '2026-10-16', regular: 8.0);
        $this->createMonthlyEntry($employee, '2026-10-30', regular: 8.0);

        $october = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-10-16'),
            Carbon::parse('2026-10-31'),
        );

        // Mismo salario base pese a distinta cantidad de días calendario.
        $this->assertEquals(1000000.0, $february['cost_summary']['base']);
        $this->assertEquals(1000000.0, $october['cost_summary']['base']);
        $this->assertEquals($february['cost_summary']['total'], $october['cost_summary']['total']);
    }

    public function test_monthly_employee_who_entered_mid_quincena_gets_prorated_base(): void
    {
        $employee = $this->makeMonthlyEmployee(2000000, 8000);

        // Ingresó el 8 de marzo; reporte del 8 al 15 (8 días comerciales).
        $this->createMonthlyEntry($employee, '2026-03-09', regular: 8.0);
        $this->createMonthlyEntry($employee, '2026-03-10', regular: 8.0);

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-08'),
            Carbon::parse('2026-03-15'),
        );

        // base = 2.000.000 × 8/30 = 533.333,33.
        $this->assertEquals(533333.33, $result['cost_summary']['base']);
        $this->assertEquals(533333.33, $result['cost_summary']['total']);
    }

    private function makeMonthlyEmployee(float $monthlyBaseSalary, float $hourlyRate): Employee
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        return Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => $monthlyBaseSalary,
            'hourly_rate' => $hourlyRate,
        ]);
    }

    private function createMonthlyEntry(Employee $employee, string $date, float $regular = 0.0, float $night = 0.0, float $overtimeDay = 0.0): void
    {
        $net = $regular + $night + $overtimeDay;

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => "{$date} 08:00:00",
            'clock_out' => "{$date} 18:00:00",
            'gross_hours' => $net,
            'break_hours' => 0,
            'net_hours' => $net,
            'regular_hours' => $regular,
            'night_hours' => $night,
            'overtime_day_hours' => $overtimeDay,
            'sunday_holiday_hours' => 0,
            'status' => 'calculated',
        ]);
    }
}
