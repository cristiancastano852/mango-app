<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWorkHours;
use App\Domain\TimeTracking\Actions\RecalculateEmployeeHours;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecalculateEmployeeHoursTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'timezone' => 'America/Bogota',
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);
    }

    private function createCalculatedEntry(string $clockIn, string $clockOut): TimeEntry
    {
        $in = Carbon::parse($clockIn, 'America/Bogota');
        $out = Carbon::parse($clockOut, 'America/Bogota');
        $gross = round($in->diffInMinutes($out) / 60, 2);

        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $in->toDateString(),
            'clock_in' => $in,
            'clock_out' => $out,
            'gross_hours' => $gross,
            'break_hours' => 0,
            'net_hours' => $gross,
            'status' => 'completed',
        ]);

        return app(CalculateWorkHours::class)->execute($entry)->fresh();
    }

    public function test_recalculation_reflects_a_changed_night_window(): void
    {
        // 19:00–22:00 con franja nocturna por defecto (21:00) → 2h regular + 1h nocturna.
        $entry = $this->createCalculatedEntry('2026-03-04 19:00', '2026-03-04 22:00');
        $this->assertEquals(2.00, (float) $entry->regular_hours);
        $this->assertEquals(1.00, (float) $entry->night_hours);

        // La empresa adelanta el nocturno a las 19:00 y recalcula el periodo.
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->update(['night_start_time' => '19:00']);

        $count = app(RecalculateEmployeeHours::class)->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $entry->refresh();
        $this->assertEquals(1, $count);
        $this->assertEquals(0.00, (float) $entry->regular_hours);
        $this->assertEquals(3.00, (float) $entry->night_hours); // 19:00–22:00 completo
    }

    public function test_recalculation_corrects_stale_buckets_without_changing_clock_times(): void
    {
        $entry = $this->createCalculatedEntry('2026-03-04 20:00', '2026-03-04 22:00');

        // Buckets "sucios" (como si vinieran de una config vieja): todo en regular.
        $entry->update(['regular_hours' => 2.00, 'night_hours' => 0.00]);

        app(RecalculateEmployeeHours::class)->execute(
            $this->employee->id,
            Carbon::parse('2026-03-04'),
            Carbon::parse('2026-03-04'),
        );

        $entry->refresh();
        // 20:00–22:00 → 1h regular + 1h nocturna (franja 21:00).
        $this->assertEquals(1.00, (float) $entry->regular_hours);
        $this->assertEquals(1.00, (float) $entry->night_hours);
        // No se tocan los fichajes.
        $this->assertEquals('2026-03-04 20:00:00', $entry->clock_in->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-03-04 22:00:00', $entry->clock_out->format('Y-m-d H:i:s'));
    }

    public function test_recalculation_preserves_edited_status(): void
    {
        $entry = $this->createCalculatedEntry('2026-03-04 08:00', '2026-03-04 16:00');
        $entry->update(['status' => 'edited', 'edited_by' => 1, 'edit_reason' => 'corrección']);

        app(RecalculateEmployeeHours::class)->execute(
            $this->employee->id,
            Carbon::parse('2026-03-04'),
            Carbon::parse('2026-03-04'),
        );

        $entry->refresh();
        $this->assertEquals('edited', $entry->status);
        $this->assertEquals('corrección', $entry->edit_reason);
    }

    public function test_recalculation_skips_open_shifts_and_counts_only_closed(): void
    {
        $this->createCalculatedEntry('2026-03-04 08:00', '2026-03-04 16:00');

        // Turno abierto (sin clock_out) no se cuenta ni se toca.
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-05',
            'clock_in' => Carbon::parse('2026-03-05 08:00', 'America/Bogota'),
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'in_progress',
        ]);

        $count = app(RecalculateEmployeeHours::class)->execute(
            $this->employee->id,
            Carbon::parse('2026-03-01'),
            Carbon::parse('2026-03-07'),
        );

        $this->assertEquals(1, $count);
    }

    public function test_recalculation_is_scoped_to_the_employee(): void
    {
        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $otherUser->assignRole('employee');
        $other = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);

        $mine = $this->createCalculatedEntry('2026-03-04 20:00', '2026-03-04 22:00');
        $in = Carbon::parse('2026-03-04 20:00', 'America/Bogota');
        $theirs = TimeEntry::create([
            'employee_id' => $other->id,
            'company_id' => $this->company->id,
            'date' => $in->toDateString(),
            'clock_in' => $in,
            'clock_out' => Carbon::parse('2026-03-04 22:00', 'America/Bogota'),
            'gross_hours' => 2,
            'break_hours' => 0,
            'net_hours' => 2,
            'regular_hours' => 99, // valor centinela que NO debe recalcularse
            'status' => 'completed',
        ]);

        $count = app(RecalculateEmployeeHours::class)->execute(
            $this->employee->id,
            Carbon::parse('2026-03-04'),
            Carbon::parse('2026-03-04'),
        );

        $this->assertEquals(1, $count);
        $this->assertEquals(99.0, (float) $theirs->refresh()->regular_hours);
        $this->assertEquals(1.00, (float) $mine->refresh()->night_hours);
    }
}
