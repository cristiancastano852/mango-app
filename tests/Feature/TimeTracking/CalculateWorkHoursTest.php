<?php

namespace Tests\Feature\TimeTracking;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWorkHours;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalculateWorkHoursTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private SurchargeRule $surchargeRule;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'timezone' => 'America/Bogota',
        ]);

        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
        ]);

        $this->surchargeRule = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->firstOrFail();
    }

    public function test_minutes_in_configured_night_range_are_classified_as_night(): void
    {
        $this->surchargeRule->update([
            'night_start_time' => '22:00',
            'night_end_time' => '05:00',
        ]);

        // Shift from 22:30 to 23:30 on a weekday — all within the night range 22:00–05:00
        $tz = 'America/Bogota';
        $clockIn = Carbon::parse('2026-03-09 22:30:00', $tz)->utc();
        $clockOut = Carbon::parse('2026-03-09 23:30:00', $tz)->utc();
        $grossHours = 1.0;

        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-09',
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'net_hours' => $grossHours,
            'break_hours' => 0,
            'status' => 'pending',
        ]);

        $result = (new CalculateWorkHours)->execute($entry);

        $this->assertEqualsWithDelta(1.0, (float) $result->night_hours, 0.02);
        $this->assertEqualsWithDelta(0.0, (float) $result->regular_hours, 0.02);
    }

    public function test_minutes_outside_configured_night_range_are_not_classified_as_night(): void
    {
        $this->surchargeRule->update([
            'night_start_time' => '22:00',
            'night_end_time' => '05:00',
        ]);

        // Shift from 21:00 to 22:00 on a weekday — all BEFORE night range starts at 22:00
        $tz = 'America/Bogota';
        $clockIn = Carbon::parse('2026-03-09 21:00:00', $tz)->utc();
        $clockOut = Carbon::parse('2026-03-09 22:00:00', $tz)->utc();
        $grossHours = 1.0;

        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-09',
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => $grossHours,
            'net_hours' => $grossHours,
            'break_hours' => 0,
            'status' => 'pending',
        ]);

        $result = (new CalculateWorkHours)->execute($entry);

        $this->assertEqualsWithDelta(0.0, (float) $result->night_hours, 0.02);
        $this->assertEqualsWithDelta(1.0, (float) $result->regular_hours, 0.02);
    }

    public function test_default_night_range_classifies_standard_colombian_hours(): void
    {
        // Default: 21:00–06:00. Shift 21:00–22:00 should be all night.
        $tz = 'America/Bogota';
        $clockIn = Carbon::parse('2026-03-09 21:00:00', $tz)->utc();
        $clockOut = Carbon::parse('2026-03-09 22:00:00', $tz)->utc();

        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-09',
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => 1.0,
            'net_hours' => 1.0,
            'break_hours' => 0,
            'status' => 'pending',
        ]);

        $result = (new CalculateWorkHours)->execute($entry);

        $this->assertEqualsWithDelta(1.0, (float) $result->night_hours, 0.02);
        $this->assertEqualsWithDelta(0.0, (float) $result->regular_hours, 0.02);
    }

    public function test_returns_entry_unchanged_when_no_clock_out(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-09',
            'clock_in' => now(),
            'clock_out' => null,
            'gross_hours' => 0,
            'net_hours' => 0,
            'break_hours' => 0,
            'status' => 'clocked_in',
        ]);

        $result = (new CalculateWorkHours)->execute($entry);

        $this->assertEquals('clocked_in', $result->status);
    }
}
