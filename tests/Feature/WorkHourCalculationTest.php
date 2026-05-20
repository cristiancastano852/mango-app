<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Holiday;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\CalculateWorkHours;
use App\Domain\TimeTracking\Actions\ClockOut;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkHourCalculationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private SurchargeRule $rules;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'timezone' => 'America/Bogota',
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $this->rules = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->first();
    }

    private function createEntry(string $clockIn, string $clockOut, float $breakHours = 0): TimeEntry
    {
        $in = Carbon::parse($clockIn, 'America/Bogota');
        $out = Carbon::parse($clockOut, 'America/Bogota');
        $grossHours = round($in->diffInMinutes($out) / 60, 2);
        $netHours = round(max(0, $grossHours - $breakHours), 2);

        return TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $in->toDateString(),
            'clock_in' => $in,
            'clock_out' => $out,
            'gross_hours' => $grossHours,
            'break_hours' => $breakHours,
            'net_hours' => $netHours,
        ]);
    }

    public function test_regular_daytime_shift(): void
    {
        // Monday 08:00–16:00 → 8h regular
        $entry = $this->createEntry('2026-03-02 08:00', '2026-03-02 16:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->sunday_holiday_hours);
        $this->assertEquals(0.00, (float) $result->overtime_hours);
        $this->assertEquals('calculated', $result->status);
    }

    public function test_night_hours_crossing_threshold(): void
    {
        // Monday 20:00–22:00 → 1h regular (20:00–21:00), 1h night (21:00–22:00)
        $entry = $this->createEntry('2026-03-02 20:00', '2026-03-02 22:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(1.00, (float) $result->regular_hours);
        $this->assertEquals(1.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->sunday_holiday_hours);
    }

    public function test_shift_crossing_midnight(): void
    {
        // Monday 22:00 – Tuesday 02:00 → 4h night
        $entry = $this->createEntry('2026-03-02 22:00', '2026-03-03 02:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(4.00, (float) $result->night_hours);
    }

    public function test_sunday_daytime_shift(): void
    {
        // Sunday 08:00–16:00 → 8h sunday_holiday
        $entry = $this->createEntry('2026-03-01 08:00', '2026-03-01 16:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(8.00, (float) $result->sunday_holiday_hours);
    }

    public function test_holiday_daytime_shift(): void
    {
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Festivo Test',
            'date' => '2026-03-02',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $entry = $this->createEntry('2026-03-02 08:00', '2026-03-02 16:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(8.00, (float) $result->sunday_holiday_hours);
    }

    public function test_overtime_partial(): void
    {
        // Same week (Mon–Thu Mar 2-5): 4 × 10h = 40h prior
        // With 42h limit → Friday Mar 6 shift 4h: first 2h regular, last 2h overtime
        $this->rules->update(['max_weekly_hours' => 42]);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
            TimeEntry::create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => $date,
                'clock_in' => Carbon::parse("{$date} 08:00", 'America/Bogota')->utc(),
                'clock_out' => Carbon::parse("{$date} 18:00", 'America/Bogota')->utc(),
                'gross_hours' => 10,
                'break_hours' => 0,
                'net_hours' => 10,
            ]);
        }

        $entry = $this->createEntry('2026-03-06 08:00', '2026-03-06 12:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->regular_hours);
        $this->assertEquals(2.00, (float) $result->overtime_hours);
    }

    public function test_all_overtime_when_exceeds_weekly_limit(): void
    {
        $this->rules->update(['max_weekly_hours' => 42]);

        // Same week (Mon–Thu Mar 2-5): 4 × 11h = 44h prior > 42h limit
        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
            TimeEntry::create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => $date,
                'clock_in' => Carbon::parse("{$date} 08:00", 'America/Bogota')->utc(),
                'clock_out' => Carbon::parse("{$date} 19:00", 'America/Bogota')->utc(),
                'gross_hours' => 11,
                'break_hours' => 0,
                'net_hours' => 11,
            ]);
        }

        $entry = $this->createEntry('2026-03-06 08:00', '2026-03-06 16:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(8.00, (float) $result->overtime_hours);
    }

    public function test_breaks_applied_proportionally(): void
    {
        // 9 gross hours, 1h break, all daytime → net = 8h regular
        $entry = $this->createEntry('2026-03-02 08:00', '2026-03-02 17:00', 1.0);

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
    }

    public function test_no_calculation_without_clock_out(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-03-02',
            'clock_in' => Carbon::parse('2026-03-02 08:00', 'America/Bogota')->utc(),
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
        ]);

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertNotEquals('calculated', $result->status);
    }

    public function test_recurring_holiday_matched_by_month_day(): void
    {
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Año Nuevo',
            'date' => '2025-01-01',
            'is_recurring' => true,
            'country' => 'CO',
        ]);

        // 2026-01-01 is Thursday but marked as recurring holiday
        $entry = $this->createEntry('2026-01-01 08:00', '2026-01-01 16:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(8.00, (float) $result->sunday_holiday_hours);
    }

    public function test_daily_limit_triggers_overtime_independent_of_weekly(): void
    {
        // Monday 08:00–18:00 = 10h net, weekly total stays at 10h (far below 42h limit)
        // Daily limit default = 8h → first 8h regular, last 2h overtime
        $entry = $this->createEntry('2026-03-02 08:00', '2026-03-02 18:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->sunday_holiday_hours);
        $this->assertEquals(2.00, (float) $result->overtime_hours);
    }

    public function test_weekly_limit_triggers_overtime_when_daily_not_exceeded(): void
    {
        // Mon–Sun: 7h/day × 6 days = 42h prior (hits weekly limit exactly)
        // Sunday 7th day: any hours should be overtime (weekly exhausted)
        $this->rules->update(['max_weekly_hours' => 42, 'max_daily_hours' => 8]);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07'] as $date) {
            TimeEntry::create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => $date,
                'clock_in' => Carbon::parse("{$date} 08:00", 'America/Bogota')->utc(),
                'clock_out' => Carbon::parse("{$date} 15:00", 'America/Bogota')->utc(),
                'gross_hours' => 7,
                'break_hours' => 0,
                'net_hours' => 7,
            ]);
        }

        // Sunday 2026-03-08: 7h shift, weekly already at 42h → all overtime
        $entry = $this->createEntry('2026-03-08 08:00', '2026-03-08 15:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(7.00, (float) $result->overtime_hours);
    }

    public function test_weekly_limit_triggers_before_daily_limit_is_reached(): void
    {
        // Prior weekly: 40h (Mon-Thu, 10h each). Prior daily for Friday: 0.
        // Friday shift 08:00-14:00 = 6h: weekly triggers at 2h, daily limit 8h is never reached.
        // Total overtime = 4h (2h regular until weekly exhausted, then 4h OT).
        $this->rules->update(['max_weekly_hours' => 42, 'max_daily_hours' => 8]);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
            TimeEntry::create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => $date,
                'clock_in' => Carbon::parse("{$date} 08:00", 'America/Bogota')->utc(),
                'clock_out' => Carbon::parse("{$date} 18:00", 'America/Bogota')->utc(),
                'gross_hours' => 10,
                'break_hours' => 0,
                'net_hours' => 10,
            ]);
        }

        $entry = $this->createEntry('2026-03-06 08:00', '2026-03-06 14:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->regular_hours);
        $this->assertEquals(4.00, (float) $result->overtime_hours);
        // Sanity: total classified equals net_hours
        $total = (float) $result->regular_hours + (float) $result->overtime_hours;
        $this->assertEquals(6.00, $total);
    }

    public function test_midnight_crossing_shift_resets_daily_counter(): void
    {
        // Shift: Monday 22:00 – Tuesday 10:00 (12h net), max_daily_hours = 8
        // Monday portion: 22:00–00:00 = 2h (all night, daily counter goes 0→2h)
        // Tuesday portion: 00:00–10:00 = 10h. Counter resets to 0 at midnight.
        //   Daily limit hit at 08:00 (0+8h). So: 8h night (00:00–06:00 + 06:00–08:00), 2h OT (08:00–10:00)
        // Net result: 2h night (Mon) + 6h night (Tue pre-dawn) + 2h regular (Tue 06–08) + 2h OT (Tue 08–10)
        $this->rules->update(['max_daily_hours' => 8, 'max_weekly_hours' => 42]);

        $entry = $this->createEntry('2026-03-02 22:00', '2026-03-03 10:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->regular_hours);  // 06:00–08:00 Tue
        $this->assertEquals(8.00, (float) $result->night_hours);    // 22:00–00:00 Mon + 00:00–06:00 Tue
        $this->assertEquals(0.00, (float) $result->sunday_holiday_hours);
        $this->assertEquals(2.00, (float) $result->overtime_hours); // 08:00–10:00 Tue (daily limit hit)

        $total = (float) $result->regular_hours
            + (float) $result->night_hours
            + (float) $result->overtime_hours;
        $this->assertEquals(12.00, $total);
    }

    public function test_custom_daily_limit_is_respected(): void
    {
        // Company configured 10h daily limit. 12h shift → 10h regular + 2h overtime.
        $this->rules->update(['max_daily_hours' => 10]);

        $entry = $this->createEntry('2026-03-02 06:00', '2026-03-02 18:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(10.00, (float) $result->regular_hours);
        $this->assertEquals(2.00, (float) $result->overtime_hours);
    }

    public function test_clock_out_integration_stores_calculated_hours(): void
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        $employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
        ]);

        $entry = TimeEntry::create([
            'employee_id' => $employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(8),
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
        ]);

        $result = app(ClockOut::class)->execute($entry);
        $fresh = TimeEntry::withoutGlobalScopes()->find($result->id);

        $this->assertEquals('calculated', $fresh->status);
        $totalClassified = (float) $fresh->regular_hours
            + (float) $fresh->night_hours
            + (float) $fresh->sunday_holiday_hours
            + (float) $fresh->overtime_hours;
        $this->assertGreaterThan(0, $totalClassified);
    }
}
