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
        $this->assertEquals(0.00, (float) $result->dominical_hours);
        $this->assertEquals(0.00, (float) $result->overtime_day_hours);
        $this->assertEquals('calculated', $result->status);
    }

    public function test_night_hours_crossing_threshold(): void
    {
        // Monday 20:00–22:00 → 1h regular (20:00–21:00), 1h night (21:00–22:00)
        $entry = $this->createEntry('2026-03-02 20:00', '2026-03-02 22:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(1.00, (float) $result->regular_hours);
        $this->assertEquals(1.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
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
        $this->assertEquals(8.00, (float) $result->dominical_hours);
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
        $this->assertEquals(8.00, (float) $result->holiday_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
    }

    public function test_overtime_partial(): void
    {
        // Same week (Mon–Thu Mar 2-5): 4 × 10h = 40h prior
        // With 42h limit → Friday Mar 6 shift 4h: first 2h regular, last 2h overtime
        $this->rules->update(['max_weekly_minutes' => 2520]);

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
        $this->assertEquals(2.00, (float) $result->overtime_day_hours);
    }

    public function test_all_overtime_when_exceeds_weekly_limit(): void
    {
        $this->rules->update(['max_weekly_minutes' => 2520]);

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
        $this->assertEquals(8.00, (float) $result->overtime_day_hours);
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
        $this->assertEquals(8.00, (float) $result->holiday_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
    }

    public function test_daily_limit_triggers_overtime_independent_of_weekly(): void
    {
        // Monday 08:00–18:00 = 10h net, weekly total stays at 10h (far below 42h limit)
        // Daily limit default = 8h → first 8h regular, last 2h overtime
        $entry = $this->createEntry('2026-03-02 08:00', '2026-03-02 18:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
        $this->assertEquals(2.00, (float) $result->overtime_day_hours);
    }

    public function test_weekly_limit_triggers_overtime_when_daily_not_exceeded(): void
    {
        // Mon–Sun: 7h/day × 6 days = 42h prior (hits weekly limit exactly)
        // Sunday 7th day: any hours should be overtime (weekly exhausted)
        $this->rules->update(['max_weekly_minutes' => 2520, 'max_daily_minutes' => 480]);

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

        // Sunday 2026-03-08: 7h diurno, weekly already at 42h → overtime diurno dominical
        $entry = $this->createEntry('2026-03-08 08:00', '2026-03-08 15:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->overtime_day_hours); // semana diurna = 0
        $this->assertEquals(7.00, (float) $result->overtime_day_dominical_hours); // domingo + extra = overtime_day_sunday
    }

    public function test_weekly_limit_triggers_before_daily_limit_is_reached(): void
    {
        // Prior weekly: 40h (Mon-Thu, 10h each). Prior daily for Friday: 0.
        // Friday shift 08:00-14:00 = 6h: weekly triggers at 2h, daily limit 8h is never reached.
        // Total overtime = 4h (2h regular until weekly exhausted, then 4h OT).
        $this->rules->update(['max_weekly_minutes' => 2520, 'max_daily_minutes' => 480]);

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
        $this->assertEquals(4.00, (float) $result->overtime_day_hours);
        // Sanity: total classified equals net_hours
        $total = (float) $result->regular_hours + (float) $result->overtime_day_hours;
        $this->assertEquals(6.00, $total);
    }

    public function test_midnight_crossing_shift_resets_daily_counter(): void
    {
        // Shift: Monday 22:00 – Tuesday 10:00 (12h net), max_daily_minutes = 480
        // Monday portion: 22:00–00:00 = 2h (all night, daily counter goes 0→2h)
        // Tuesday portion: 00:00–10:00 = 10h. Counter resets to 0 at midnight.
        //   Daily limit hit at 08:00 (0+8h). So: 8h night (00:00–06:00 + 06:00–08:00), 2h OT (08:00–10:00)
        // Net result: 2h night (Mon) + 6h night (Tue pre-dawn) + 2h regular (Tue 06–08) + 2h OT (Tue 08–10)
        $this->rules->update(['max_daily_minutes' => 480, 'max_weekly_minutes' => 2520]);

        $entry = $this->createEntry('2026-03-02 22:00', '2026-03-03 10:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->regular_hours);  // 06:00–08:00 Tue
        $this->assertEquals(8.00, (float) $result->night_hours);    // 22:00–00:00 Mon + 00:00–06:00 Tue
        $this->assertEquals(0.00, (float) $result->dominical_hours);
        $this->assertEquals(2.00, (float) $result->overtime_day_hours); // 08:00–10:00 Tue (daily limit hit)

        $total = (float) $result->regular_hours
            + (float) $result->night_hours
            + (float) $result->overtime_day_hours;
        $this->assertEquals(12.00, $total);
    }

    public function test_custom_daily_limit_is_respected(): void
    {
        // Company configured 10h daily limit. 12h shift → 10h regular + 2h overtime.
        $this->rules->update(['max_daily_minutes' => 600]);

        $entry = $this->createEntry('2026-03-02 06:00', '2026-03-02 18:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(10.00, (float) $result->regular_hours);
        $this->assertEquals(2.00, (float) $result->overtime_day_hours);
    }

    public function test_daily_limit_with_minutes_defines_breakpoint(): void
    {
        // Company configured 7h20m (440 min) daily limit. 8h day shift → 7h20m regular + 40m overtime.
        $this->rules->update(['max_daily_minutes' => 440]);

        $entry = $this->createEntry('2026-03-02 06:00', '2026-03-02 14:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        // 440 min = 7.333… h regular; 40 min = 0.667… h overtime diurno.
        $this->assertEqualsWithDelta(7.33, (float) $result->regular_hours, 0.01);
        $this->assertEqualsWithDelta(0.67, (float) $result->overtime_day_hours, 0.01);
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
            + (float) $fresh->dominical_hours
            + (float) $fresh->night_dominical_hours
            + (float) $fresh->holiday_hours
            + (float) $fresh->night_holiday_hours
            + (float) $fresh->overtime_day_hours
            + (float) $fresh->overtime_night_hours
            + (float) $fresh->overtime_day_dominical_hours
            + (float) $fresh->overtime_night_dominical_hours
            + (float) $fresh->overtime_day_holiday_hours
            + (float) $fresh->overtime_night_holiday_hours;
        $this->assertEqualsWithDelta((float) $fresh->net_hours, $totalClassified, 0.02);
        $this->assertGreaterThan(0, $totalClassified);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Casos nuevos: 8 tipos de hora
    // ──────────────────────────────────────────────────────────────────────

    public function test_sunday_night_classified_as_night_sunday(): void
    {
        // Caso 1.4: domingo 21:00–23:00, sin horas previas → 2h night_sunday
        $entry = $this->createEntry('2026-03-01 21:00', '2026-03-01 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->night_dominical_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
    }

    public function test_overtime_night_when_daily_exhausted_and_nighttime(): void
    {
        // Caso 1.6 / 3.3 parcial: lunes 10:00–23:00 (turno único)
        // 10:00–18:00 = 8h regular (límite diario), 18:00–21:00 = 3h overtime_day, 21:00–23:00 = 2h overtime_night
        $entry = $this->createEntry('2026-03-02 10:00', '2026-03-02 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(3.00, (float) $result->overtime_day_hours);
        $this->assertEquals(2.00, (float) $result->overtime_night_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
    }

    public function test_overtime_day_sunday_when_daily_limit_exceeded(): void
    {
        // Caso 4.2: domingo 06:00–18:00 → 8h sunday_holiday + 4h overtime_day_sunday
        $entry = $this->createEntry('2026-03-01 06:00', '2026-03-01 18:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->dominical_hours);
        $this->assertEquals(4.00, (float) $result->overtime_day_dominical_hours);
        $this->assertEquals(0.00, (float) $result->overtime_day_hours);
    }

    public function test_overtime_night_sunday_all_three_conditions_met(): void
    {
        // Caso 1.8 / 4.3 parcial: domingo 10:00–23:00 (turno único)
        // 10:00–18:00 = 8h sunday_holiday (límite), 18:00–21:00 = 3h overtime_day_sunday, 21:00–23:00 = 2h overtime_night_sunday
        $entry = $this->createEntry('2026-03-01 10:00', '2026-03-01 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->dominical_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(3.00, (float) $result->overtime_day_dominical_hours);
        $this->assertEquals(2.00, (float) $result->overtime_night_dominical_hours);
        $this->assertEquals(0.00, (float) $result->night_dominical_hours);
        $this->assertEquals(0.00, (float) $result->overtime_night_hours);
    }

    public function test_long_weekday_shift_produces_regular_overtime_day_overtime_night(): void
    {
        // Caso 3.3: lunes 06:00–23:00 → 8h regular + 7h overtime_day + 2h overtime_night
        $entry = $this->createEntry('2026-03-02 06:00', '2026-03-02 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->regular_hours);
        $this->assertEquals(7.00, (float) $result->overtime_day_hours);
        $this->assertEquals(2.00, (float) $result->overtime_night_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
    }

    public function test_shift_crossing_midnight_resets_daily_and_returns_to_night(): void
    {
        // Caso 3.4: lunes 14:00–02:00 martes
        // 14:00–21:00 = 7h ordinaria, 21:00–22:00 = 1h nocturna (límite), 22:00–00:00 = 2h overtime_night, 00:00–02:00 = 2h nocturna
        $entry = $this->createEntry('2026-03-02 14:00', '2026-03-03 02:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(7.00, (float) $result->regular_hours);
        $this->assertEquals(3.00, (float) $result->night_hours);  // 1h lun + 2h mar
        $this->assertEquals(2.00, (float) $result->overtime_night_hours);
        $this->assertEquals(0.00, (float) $result->overtime_day_hours);
    }

    public function test_full_sunday_shift_produces_all_four_sunday_types(): void
    {
        // Caso 4.3: domingo 06:00–23:00 → sunday_holiday=8, overtime_day_sunday=7, overtime_night_sunday=2
        $entry = $this->createEntry('2026-03-01 06:00', '2026-03-01 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(8.00, (float) $result->dominical_hours);
        $this->assertEquals(7.00, (float) $result->overtime_day_dominical_hours);
        $this->assertEquals(2.00, (float) $result->overtime_night_dominical_hours);
        $this->assertEquals(0.00, (float) $result->night_dominical_hours); // límite agotado antes de las 21:00
        $this->assertEquals(0.00, (float) $result->regular_hours);
    }

    public function test_sunday_shift_crossing_night_threshold(): void
    {
        // Caso 4.1: domingo 19:00–23:00 → 2h sunday_holiday + 2h night_sunday
        $entry = $this->createEntry('2026-03-01 19:00', '2026-03-01 23:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(2.00, (float) $result->dominical_hours);
        $this->assertEquals(2.00, (float) $result->night_dominical_hours);
        $this->assertEquals(0.00, (float) $result->night_hours);
        $this->assertEquals(0.00, (float) $result->regular_hours);
    }

    public function test_holiday_night_shift_crossing_into_weekday(): void
    {
        // Caso 4.5: festivo jueves 21:00–01:00 viernes → 3h night_sunday + 1h night
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Festivo Test',
            'date' => '2026-03-05',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $entry = $this->createEntry('2026-03-05 21:00', '2026-03-06 01:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEquals(3.00, (float) $result->night_holiday_hours); // 21:00–00:00 festivo
        $this->assertEquals(1.00, (float) $result->night_hours);         // 00:00–01:00 viernes hábil
        $this->assertEquals(0.00, (float) $result->dominical_hours);
    }

    public function test_saturday_night_crossing_to_sunday_changes_surcharge_type(): void
    {
        // Caso 5.1: sábado 22:00–04:00 domingo → 2h night + 4h night_sunday
        $entry = $this->createEntry('2026-02-28 22:00', '2026-03-01 04:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEqualsWithDelta(2.00, (float) $result->night_hours, 0.02);
        $this->assertEqualsWithDelta(4.00, (float) $result->night_dominical_hours, 0.02);
        $this->assertEquals(0.00, (float) $result->regular_hours);
    }

    public function test_sunday_night_crossing_to_monday_changes_surcharge_type(): void
    {
        // Caso 5.2: domingo 22:00–04:00 lunes → 2h night_sunday + 4h night
        $entry = $this->createEntry('2026-03-01 22:00', '2026-03-02 04:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEqualsWithDelta(2.00, (float) $result->night_dominical_hours, 0.02);
        $this->assertEqualsWithDelta(4.00, (float) $result->night_hours, 0.02);
        $this->assertEquals(0.00, (float) $result->regular_hours);
    }

    public function test_saturday_evening_crossing_night_then_sunday_midnight(): void
    {
        // Caso 5.3: sábado 20:00–04:00 domingo → 1h regular + 3h night + 4h night_sunday
        $entry = $this->createEntry('2026-02-28 20:00', '2026-03-01 04:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEqualsWithDelta(1.00, (float) $result->regular_hours, 0.02);
        $this->assertEqualsWithDelta(3.00, (float) $result->night_hours, 0.02);
        $this->assertEqualsWithDelta(4.00, (float) $result->night_dominical_hours, 0.02);
    }

    public function test_weekly_limit_exhausted_during_night_shift_produces_overtime_night(): void
    {
        // Caso 6.2: lun-jue 4 × 10h = 40h. Viernes 20:00–02:00 sáb, restante semanal = 2h
        // 20:00–21:00 = 1h ordinaria, 21:00–22:00 = 1h nocturna (semanal agotado), 22:00–00:00 = 2h overtime_night, 00:00–02:00 = 2h night
        $this->rules->update(['max_weekly_minutes' => 2520, 'max_daily_minutes' => 480]);

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

        $entry = $this->createEntry('2026-03-06 20:00', '2026-03-07 02:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        // 20:00–21:00 Fri = 1h regular (semanal 40→41h, diurno)
        // 21:00–22:00 Fri = 1h night (semanal 41→42h, nocturno, límite exacto a las 22:00)
        // 22:00–00:00 Fri = 2h overtime_night (semanal >42h, nocturno)
        // 00:00–02:00 Sat = 2h overtime_night (semanal sigue >42h tras reset diario)
        $this->assertEquals(1.00, (float) $result->regular_hours);
        $this->assertEquals(1.00, (float) $result->night_hours);
        $this->assertEquals(4.00, (float) $result->overtime_night_hours);
    }

    public function test_weekly_limit_exhausted_on_sunday_produces_overtime_day_sunday(): void
    {
        // Caso 6.3: lun-sáb 40h acumuladas. Domingo 08:00–12:00 → 2h sunday_holiday + 2h overtime_day_sunday
        $this->rules->update(['max_weekly_minutes' => 2520, 'max_daily_minutes' => 480]);

        foreach (['2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05', '2026-03-06', '2026-03-07'] as $date) {
            TimeEntry::create([
                'employee_id' => $this->employee->id,
                'company_id' => $this->company->id,
                'date' => $date,
                'clock_in' => Carbon::parse("{$date} 09:00", 'America/Bogota')->utc(),
                'clock_out' => Carbon::parse("{$date} 15:40", 'America/Bogota')->utc(), // ~6.67h each = 40h total
                'gross_hours' => 6.67,
                'break_hours' => 0,
                'net_hours' => 6.67,
            ]);
        }

        // Domingo: acumulado = 40.02h ≈ 40h, restante para llegar a 42h = 2h
        $entry = $this->createEntry('2026-03-08 08:00', '2026-03-08 12:00');

        $result = app(CalculateWorkHours::class)->execute($entry);

        $this->assertEqualsWithDelta(2.00, (float) $result->dominical_hours, 0.05);
        $this->assertEqualsWithDelta(2.00, (float) $result->overtime_day_dominical_hours, 0.05);
        $this->assertEquals(0.00, (float) $result->regular_hours);
        $this->assertEquals(0.00, (float) $result->overtime_day_hours);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Día dominical configurable y separación festivo/dominical (12 tipos)
    // ──────────────────────────────────────────────────────────────────────

    public function test_configurable_dominical_weekday_shifts_surcharge_to_tuesday(): void
    {
        // Dominical = martes (2). Martes diurno → dominical; domingo → regular.
        $this->rules->update(['dominical_weekday' => Carbon::TUESDAY]);

        $tuesday = app(CalculateWorkHours::class)->execute($this->createEntry('2026-03-03 08:00', '2026-03-03 16:00'));
        $this->assertEquals(8.00, (float) $tuesday->dominical_hours);
        $this->assertEquals(0.00, (float) $tuesday->regular_hours);

        $sunday = app(CalculateWorkHours::class)->execute($this->createEntry('2026-03-08 08:00', '2026-03-08 16:00'));
        $this->assertEquals(8.00, (float) $sunday->regular_hours);
        $this->assertEquals(0.00, (float) $sunday->dominical_hours);
    }

    public function test_holiday_on_dominical_day_is_classified_as_holiday(): void
    {
        // Festivo gana sobre dominical: un domingo festivo cuenta como holiday, no dominical.
        Holiday::create([
            'company_id' => $this->company->id,
            'name' => 'Festivo en domingo',
            'date' => '2026-03-08',
            'is_recurring' => false,
            'country' => 'CO',
        ]);

        $result = app(CalculateWorkHours::class)->execute($this->createEntry('2026-03-08 08:00', '2026-03-08 16:00'));

        $this->assertEquals(8.00, (float) $result->holiday_hours);
        $this->assertEquals(0.00, (float) $result->dominical_hours);
    }

    public function test_sum_of_twelve_buckets_equals_net_hours(): void
    {
        // Domingo largo con varios tipos: la suma de los 12 buckets == net_hours.
        $this->rules->update(['max_weekly_minutes' => 2520, 'max_daily_minutes' => 480]);
        $result = app(CalculateWorkHours::class)->execute($this->createEntry('2026-03-08 06:00', '2026-03-08 23:00'));

        $sum = (float) $result->regular_hours + (float) $result->night_hours
            + (float) $result->dominical_hours + (float) $result->night_dominical_hours
            + (float) $result->holiday_hours + (float) $result->night_holiday_hours
            + (float) $result->overtime_day_hours + (float) $result->overtime_night_hours
            + (float) $result->overtime_day_dominical_hours + (float) $result->overtime_night_dominical_hours
            + (float) $result->overtime_day_holiday_hours + (float) $result->overtime_night_holiday_hours;

        $this->assertEqualsWithDelta((float) $result->net_hours, $sum, 0.02);
    }
}
