<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;
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
                'dominical_hours' => 0,
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

    public function test_pay_night_dominical_off_folds_night_dominical_into_night(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['pay_night_dominical' => false]);

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-01',
            'clock_in' => '2026-03-01 20:00:00',
            'clock_out' => '2026-03-01 23:00:00',
            'gross_hours' => 6.0,
            'break_hours' => 0,
            'net_hours' => 6.0,
            'night_hours' => 2.0,
            'night_dominical_hours' => 4.0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-01'),
        );

        $cost = $result['cost_summary'];

        // (2 + 4) × 10000 × 1.35 = 81000 todo en el nocturno; el premium queda en 0.
        $this->assertEquals(81000.0, $cost['night']);
        $this->assertEquals(0.0, $cost['night_dominical']);
        $this->assertFalse($cost['pay_night_dominical']);

        $byType = collect($cost['details'])->keyBy('type');
        $this->assertEquals(6.0, $byType['night']['hours']);
        $this->assertEquals(0.0, $byType['night_dominical']['hours']);
        $this->assertEquals(0.0, $byType['night_dominical']['subtotal']);

        // El total del reporte coincide con el nocturno fundido.
        $this->assertEquals(81000.0, $cost['total']);
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

    public function test_excludes_soft_deleted_entries(): void
    {
        // Registro activo
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        // Registro borrado (soft-delete) con una pausa: no debe contar en totales ni pausas
        $deleted = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-06',
            'clock_in' => '2026-03-06 08:00:00',
            'clock_out' => '2026-03-06 17:00:00',
            'gross_hours' => 9.0,
            'break_hours' => 1.0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $lunchType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $deleted->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $lunchType->id,
            'started_at' => '2026-03-06 12:00:00',
            'ended_at' => '2026-03-06 13:00:00',
            'duration_minutes' => 60,
        ]);

        $deleted->delete();

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(1, $result['totals']['days_worked']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);
        $this->assertCount(1, $result['daily_breakdown']);
        $this->assertEmpty($result['breaks_by_type']);
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

    public function test_daily_breakdown_includes_schedule_and_breaks_detail(): void
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

        $coffeeType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Café',
            'slug' => 'cafe',
            'icon' => '☕',
            'color' => '#4CAF50',
            'is_paid' => true,
            'is_active' => true,
        ]);

        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 07:00:00',
            'clock_out' => '2026-03-05 16:11:00',
            'gross_hours' => 9.18,
            'break_hours' => 1.25,
            'net_hours' => 7.93,
            'regular_hours' => 7.93,
            'status' => 'calculated',
        ]);

        // Creadas en desorden para verificar orden por started_at.
        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $coffeeType->id,
            'started_at' => '2026-03-05 15:00:00',
            'ended_at' => '2026-03-05 15:15:00',
            'duration_minutes' => 15,
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $lunchType->id,
            'started_at' => '2026-03-05 12:00:00',
            'ended_at' => '2026-03-05 13:00:00',
            'duration_minutes' => 60,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertCount(1, $result['daily_breakdown']);

        $day = $result['daily_breakdown'][0];
        $this->assertEquals('2026-03-05', $day['date']);
        $this->assertEquals('2026-03-05T07:00:00-05:00', $day['clock_in']);
        $this->assertEquals('2026-03-05T16:11:00-05:00', $day['clock_out']);
        $this->assertEquals('calculated', $day['status']);
        $this->assertEquals(9.18, $day['gross_hours']);
        $this->assertEquals(1.25, $day['break_hours']);
        // Solo el café (15 min) es pagado; el almuerzo (60 min) no.
        $this->assertEquals(0.25, $day['paid_break_hours']);
        $this->assertEquals(7.93, $day['net_hours']);
        $this->assertEquals(7.93, $day['regular_hours']);
        $this->assertEquals(0.0, $day['night_hours']);

        // Pausas ordenadas por started_at, con todos los campos del tipo.
        $this->assertCount(2, $day['breaks']);
        $this->assertEquals([
            'name' => 'Almuerzo',
            'icon' => '🍽️',
            'color' => '#FF9800',
            'is_paid' => false,
            'started_at' => '2026-03-05T12:00:00-05:00',
            'ended_at' => '2026-03-05T13:00:00-05:00',
            'duration_minutes' => 60,
            'overage_minutes' => 0,
            'in_progress' => false,
        ], $day['breaks'][0]);
        $this->assertEquals('Café', $day['breaks'][1]['name']);
        $this->assertTrue($day['breaks'][1]['is_paid']);
        $this->assertEquals(15, $day['breaks'][1]['duration_minutes']);
    }

    public function test_report_exposes_paid_break_overage(): void
    {
        $paidType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Descanso',
            'slug' => 'descanso',
            'icon' => '☕',
            'color' => '#3B82F6',
            'is_paid' => true,
            'max_duration_minutes' => 15,
            'is_active' => true,
        ]);

        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 12:00:00',
            'clock_out' => '2026-03-05 20:00:00',
            'gross_hours' => 8.0,
            'break_hours' => 0.0,
            'paid_break_overage_hours' => 0.17,
            'net_hours' => 7.83,
            'regular_hours' => 7.83,
            'status' => 'calculated',
        ]);

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $paidType->id,
            'started_at' => '2026-03-05 14:00:00',
            'ended_at' => '2026-03-05 14:25:00',
            'duration_minutes' => 25,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(0.17, $result['totals']['paid_break_overage_hours']);

        $day = $result['daily_breakdown'][0];
        $this->assertEquals(0.17, $day['paid_break_overage_hours']);
        $this->assertEquals(10, $day['breaks'][0]['overage_minutes']);
    }

    public function test_daily_breakdown_marks_break_without_end_as_in_progress(): void
    {
        $breakType = BreakType::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Descanso',
            'slug' => 'descanso',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $entry = TimeEntry::withoutGlobalScopes()->create([
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

        BreakEntry::withoutGlobalScopes()->create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $breakType->id,
            'started_at' => '2026-03-05 12:00:00',
            'ended_at' => null,
            'duration_minutes' => null,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $break = $result['daily_breakdown'][0]['breaks'][0];
        $this->assertTrue($break['in_progress']);
        $this->assertNull($break['ended_at']);
        $this->assertNull($break['duration_minutes']);
    }

    public function test_daily_breakdown_includes_in_progress_entry_without_counting_totals(): void
    {
        // Turno finalizado.
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-04',
            'clock_in' => '2026-03-04 08:00:00',
            'clock_out' => '2026-03-04 16:00:00',
            'gross_hours' => 8.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        // Turno abierto (sin clock_out).
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
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

        // Los totales solo consideran el turno finalizado.
        $this->assertEquals(1, $result['totals']['days_worked']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);

        // El breakdown incluye ambos días; el abierto va marcado y sin horas.
        $this->assertCount(2, $result['daily_breakdown']);
        $open = $result['daily_breakdown'][1];
        $this->assertEquals('2026-03-05', $open['date']);
        $this->assertEquals('in_progress', $open['status']);
        $this->assertEquals('2026-03-05T08:00:00-05:00', $open['clock_in']);
        $this->assertNull($open['clock_out']);
        $this->assertNull($open['net_hours']);
        $this->assertNull($open['gross_hours']);
        $this->assertNull($open['paid_break_hours']);
    }

    public function test_export_only_data_is_omitted_when_not_requested(): void
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

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
            payOvertime: true,
            includeDailyBreakdown: false,
            includeBreaksByType: false,
        );

        // El desglose diario y las pausas por tipo no se calculan (solo los usan los
        // exports): las llaves siguen presentes pero como arrays vacíos. Los totales sí.
        $this->assertEmpty($result['daily_breakdown']);
        $this->assertEmpty($result['breaks_by_type']);
        $this->assertEquals(1, $result['totals']['days_worked']);
        $this->assertEquals(8.0, $result['totals']['net_hours']);
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
            'dominical_hours' => 0,
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

    public function test_monthly_employee_with_flag_on_gets_prorated_transport_allowance(): void
    {
        $this->setCompanyTransportAllowance(240000);
        $employee = $this->makeMonthlyEmployee(2000000, 8000, receivesTransportAllowance: true);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
            $this->createMonthlyEntry($employee, $date, regular: 8.0);
        }

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        // Quincena completa → medio auxilio: 240.000 / 2 = 120.000.
        $this->assertEquals(120000.0, $result['cost_summary']['transport_allowance']);
        // total = base (1.000.000) + auxilio (120.000).
        $this->assertEquals(1000000.0 + 120000.0, $result['cost_summary']['total']);
    }

    public function test_monthly_employee_with_flag_off_gets_no_transport_allowance(): void
    {
        $this->setCompanyTransportAllowance(240000);
        $employee = $this->makeMonthlyEmployee(2000000, 8000, receivesTransportAllowance: false);

        $this->createMonthlyEntry($employee, '2026-03-02', regular: 8.0);

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertEquals(0.0, $result['cost_summary']['transport_allowance']);
        $this->assertEquals(1000000.0, $result['cost_summary']['total']);
    }

    public function test_hourly_employee_never_gets_transport_allowance(): void
    {
        $this->setCompanyTransportAllowance(240000);

        // El empleado por hora del setUp (flag default true) no debe recibir auxilio.
        $this->createMonthlyEntry($this->employee, '2026-03-02', regular: 8.0);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertEquals(0.0, $result['cost_summary']['transport_allowance']);
    }

    // ----------------------------------------------------------------------------------
    // Seguridad social a cargo del empleado (4% salud + 4% pensión sobre el IBC).
    // ----------------------------------------------------------------------------------

    public function test_hourly_report_includes_social_security_deduction(): void
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
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $cost = $result['cost_summary'];

        // total = 8h × 10.000 = 80.000; hourly → IBC = total.
        $this->assertEquals(80000.0, $cost['total']);
        $this->assertEquals(80000.0, $cost['social_security_base']);
        $this->assertEquals(3200.0, $cost['health_deduction']);  // 4%
        $this->assertEquals(3200.0, $cost['pension_deduction']); // 4%
        $this->assertEquals(73600.0, $cost['net_pay']);
    }

    public function test_monthly_report_ibc_excludes_transport_allowance(): void
    {
        $this->setCompanyTransportAllowance(240000);
        $employee = $this->makeMonthlyEmployee(2000000, 8000, receivesTransportAllowance: true);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
            $this->createMonthlyEntry($employee, $date, regular: 8.0);
        }

        $result = $this->action->execute(
            $employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $cost = $result['cost_summary'];

        // total = base 1.000.000 + auxilio 120.000; IBC = total − auxilio = 1.000.000.
        $this->assertEquals(1120000.0, $cost['total']);
        $this->assertEquals(1000000.0, $cost['social_security_base']);
        $this->assertEquals(40000.0, $cost['health_deduction']);  // 4%
        $this->assertEquals(40000.0, $cost['pension_deduction']); // 4%
        $this->assertEquals(1120000.0 - 80000.0, $cost['net_pay']);
    }

    // ----------------------------------------------------------------------------------
    // Ajustes de nómina (bonos/deducciones) aplicados después del neto a pagar.
    // ----------------------------------------------------------------------------------

    public function test_report_applies_adjustments_within_period(): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => '2026-03-05 08:00:00',
            'clock_out' => '2026-03-05 16:00:00',
            'gross_hours' => 8.0,
            'break_hours' => 0,
            'net_hours' => 8.0,
            'regular_hours' => 8.0,
            'status' => 'calculated',
        ]);

        EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-03-06',
            'amount' => 100000,
        ]);
        EmployeeAdjustment::factory()->deduction()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-03-07',
            'amount' => 30000,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $cost = $result['cost_summary'];
        $this->assertEquals(100000.0, $cost['bonus_total']);
        $this->assertEquals(30000.0, $cost['deduction_total']);
        $this->assertEquals($cost['net_pay'] + 100000.0 - 30000.0, $cost['final_pay']);
        $this->assertCount(2, $result['adjustments']);
    }

    public function test_report_ignores_adjustments_outside_period(): void
    {
        EmployeeAdjustment::factory()->bonus()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'date' => '2026-04-02',
            'amount' => 500000,
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertEquals(0.0, $result['cost_summary']['bonus_total']);
        $this->assertEquals($result['cost_summary']['net_pay'], $result['cost_summary']['final_pay']);
        $this->assertCount(0, $result['adjustments']);
    }

    private function setCompanyTransportAllowance(float $value): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['transport_allowance' => $value]);
    }

    private function makeMonthlyEmployee(float $monthlyBaseSalary, float $hourlyRate, bool $receivesTransportAllowance = false): Employee
    {
        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        // El auxilio se desactiva por defecto en este helper para que las pruebas de
        // prorrateo de salario base midan solo la base; las pruebas de auxilio lo activan.
        return Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'salary_type' => 'monthly',
            'monthly_base_salary' => $monthlyBaseSalary,
            'hourly_rate' => $hourlyRate,
            'receives_transport_allowance' => $receivesTransportAllowance,
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
            'dominical_hours' => 0,
            'status' => 'calculated',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // overtime_payable_hours — cap sobre bolsa única (3 flags premium en off)
    // ──────────────────────────────────────────────────────────────────────────

    private function setUnifiedOvertimeRules(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update([
                'pay_overtime_dominical' => false,
                'pay_overtime_holiday' => false,
                'pay_overtime_night' => false,
            ]);
    }

    public function test_overtime_payable_hours_caps_cost_to_fewer_hours(): void
    {
        $this->setUnifiedOvertimeRules();

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-02',
            'clock_in' => '2026-06-02 08:00:00',
            'clock_out' => '2026-06-02 20:00:00',
            'gross_hours' => 12.0,
            'break_hours' => 0,
            'net_hours' => 12.0,
            'regular_hours' => 8.0,
            'overtime_day_hours' => 4.0, // 4h extra
            'status' => 'calculated',
        ]);

        // Pagar solo 2 de las 4h extra
        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
            overtimePayableHours: 2.0,
        );

        // 2h × 10000 × 1.25 = 25000 (no 50000)
        $this->assertEquals(25000.0, $result['cost_summary']['overtime_day']);
        // Las horas trabajadas siguen mostrando 4h
        $this->assertEquals(4.0, $result['totals']['overtime_day_hours']);
        $this->assertEquals(2.0, $result['cost_summary']['overtime_payable_hours']);
        $this->assertEquals(4.0, $result['cost_summary']['overtime_worked_hours']);
        $this->assertTrue($result['cost_summary']['overtime_unified']);
    }

    public function test_overtime_payable_hours_null_pays_all(): void
    {
        $this->setUnifiedOvertimeRules();

        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-02',
            'clock_in' => '2026-06-02 08:00:00',
            'clock_out' => '2026-06-02 20:00:00',
            'gross_hours' => 12.0,
            'break_hours' => 0,
            'net_hours' => 12.0,
            'regular_hours' => 8.0,
            'overtime_day_hours' => 4.0,
            'status' => 'calculated',
        ]);

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
            overtimePayableHours: null,
        );

        // null → paga todas (4h × 10000 × 1.25 = 50000)
        $this->assertEquals(50000.0, $result['cost_summary']['overtime_day']);
        $this->assertNull($result['cost_summary']['overtime_payable_hours']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // night_settlement deferred — recargo nocturno del día de corte diferido
    // ──────────────────────────────────────────────────────────────────────────

    private function setDeferredNightMode(): void
    {
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['night_settlement_mode' => 'deferred']);
    }

    private function createNightEntry(string $date, float $nightHours): void
    {
        TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => "{$date} 21:00:00",
            'clock_out' => "{$date} 23:00:00",
            'gross_hours' => $nightHours,
            'break_hours' => 0,
            'net_hours' => $nightHours,
            'regular_hours' => 0,
            'night_hours' => $nightHours,
            'status' => 'calculated',
        ]);
    }

    public function test_cutoff_day_night_surcharge_is_deferred_out_of_the_period(): void
    {
        $this->setDeferredNightMode();
        $this->createNightEntry('2026-06-14', 4.0); // dentro de la ventana
        $this->createNightEntry('2026-06-15', 2.0); // día de corte → se difiere

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        // 14: base+35% (54000) ; 15: solo base (20000) = 74000
        $this->assertEquals(74000.0, $result['cost_summary']['night']);
        $this->assertTrue($result['night_settlement']['deferred']);
        $this->assertEquals('2026-06-14', $result['night_settlement']['end']);

        // La fila del 15 (día de corte) queda marcada como diferida.
        $cutoffRow = collect($result['daily_breakdown'])->firstWhere('date', '2026-06-15');
        $this->assertTrue($cutoffRow['night_deferred']);
    }

    public function test_deferred_cutoff_surcharge_is_paid_in_the_next_period(): void
    {
        $this->setDeferredNightMode();
        $this->createNightEntry('2026-06-15', 2.0); // corte de la 1ª quincena
        $this->createNightEntry('2026-06-20', 3.0); // turno normal de la 2ª quincena

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-16'),
            Carbon::parse('2026-06-30'),
        );

        // 20: base+35% (40500) + recargo diferido del 15: 2h×0.35×10000 (7000) = 47500
        $this->assertEquals(47500.0, $result['cost_summary']['night']);
        $this->assertEquals('2026-06-15', $result['night_settlement']['start']);
    }

    public function test_immediate_mode_pays_cutoff_day_night_surcharge_normally(): void
    {
        $this->createNightEntry('2026-06-15', 2.0); // default immediate

        $result = $this->action->execute(
            $this->employee->id,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-15'),
        );

        // Sin diferimiento: 2h × 10000 × 1.35 = 27000
        $this->assertEquals(27000.0, $result['cost_summary']['night']);
        $this->assertFalse($result['night_settlement']['deferred']);
    }
}
