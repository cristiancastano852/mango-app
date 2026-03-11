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
                'overtime_hours' => 0,
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
            'overtime_hours' => 0,
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
}
