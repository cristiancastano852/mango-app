<?php

namespace Tests\Feature\TimeTracking;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\SurchargeRule;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresión del incidente de duración de pausas negativa (2026-06-05).
 *
 * Reproduce el turno real que destapó el bug:
 *   - Check-in   14:10:39
 *   - Inicia pausa (NO pagada) 16:33:35
 *   - Finaliza pausa           16:48:42  (15 min reales)
 *   - Check-out  21:35:03
 *
 * Con el bug de Carbon 3 (orden invertido en diffInMinutes) la pausa se guardaba
 * con duration_minutes = -15, lo que daba break_hours = -0.25 y net_hours (7.66) MAYOR
 * que gross_hours (7.41). Tras el fix la duración es +15 y el neto se descuenta bien.
 *
 * @see docs/incidente-duracion-pausas-negativa-2026-06-05.md
 */
class NegativeBreakDurationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private Company $company;

    private BreakType $unpaidBreakType;

    private BreakType $paidBreakType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        // La SurchargeRule se crea por observer al crear la company; ajustamos la
        // franja nocturna a las 19:00 (como la empresa real del incidente).
        SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->firstOrFail()
            ->update([
                'night_start_time' => '19:00',
                'night_end_time' => '06:00',
                'max_daily_hours' => 8,
                'max_weekly_hours' => 42,
            ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $this->unpaidBreakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->paidBreakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo pagado',
            'slug' => 'almuerzo-pagado',
            'is_paid' => true,
            'is_active' => true,
        ]);
    }

    public function test_real_shift_with_unpaid_break_calculates_correctly(): void
    {
        // Check-in 14:10:39
        $this->travelTo(Carbon::parse('2026-06-05 14:10:39'));
        $this->actingAs($this->user)->post(route('time-clock.clock-in'));

        // Inicia pausa 16:33:35
        $this->travelTo(Carbon::parse('2026-06-05 16:33:35'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $this->unpaidBreakType->id,
        ]);

        // Finaliza pausa 16:48:42 (15 min reales)
        $this->travelTo(Carbon::parse('2026-06-05 16:48:42'));
        $this->actingAs($this->user)->post(route('time-clock.break.end'));

        // Check-out 21:35:03
        $this->travelTo(Carbon::parse('2026-06-05 21:35:03'));
        $this->actingAs($this->user)->post(route('time-clock.clock-out'));

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->first();
        $break = $this->employee->breaks()->first();

        // La pausa se guarda con duración POSITIVA (antes del fix era -15).
        $this->assertSame(15, $break->duration_minutes);

        // gross = 14:10:39 → 21:35:03 = 7h 24m 24s.
        $this->assertEqualsWithDelta(7.41, (float) $entry->gross_hours, 0.01);

        // break_hours = 15 min no pagados = 0.25h (antes era -0.25).
        $this->assertSame(0.25, (float) $entry->break_hours);

        // net = gross - break = 7.41 - 0.25 = 7.16 (antes salía 7.66, inflado).
        $this->assertEqualsWithDelta(7.16, (float) $entry->net_hours, 0.01);

        // Distribución: diurno hasta 19:00, nocturno 19:00 → 21:35, sin extras.
        $this->assertEqualsWithDelta(4.66, (float) $entry->regular_hours, 0.03);
        $this->assertEqualsWithDelta(2.50, (float) $entry->night_hours, 0.03);
        $this->assertSame(0.0, (float) $entry->sunday_holiday_hours);
        $this->assertSame(0.0, (float) $entry->overtime_day_hours);
        $this->assertSame(0.0, (float) $entry->overtime_night_hours);

        // Invariante que el bug rompía: el neto nunca supera el bruto.
        $this->assertLessThanOrEqual((float) $entry->gross_hours, (float) $entry->net_hours);

        // Los 8 buckets suman el neto.
        $bucketsSum = (float) $entry->regular_hours
            + (float) $entry->night_hours
            + (float) $entry->sunday_holiday_hours
            + (float) $entry->night_sunday_hours
            + (float) $entry->overtime_day_hours
            + (float) $entry->overtime_night_hours
            + (float) $entry->overtime_day_sunday_hours
            + (float) $entry->overtime_night_sunday_hours;
        $this->assertEqualsWithDelta((float) $entry->net_hours, $bucketsSum, 0.02);
    }

    public function test_real_shift_with_paid_break_does_not_deduct_net(): void
    {
        // Mismo turno, pero el almuerzo es una pausa PAGADA.
        // Check-in 14:10:39
        $this->travelTo(Carbon::parse('2026-06-05 14:10:39'));
        $this->actingAs($this->user)->post(route('time-clock.clock-in'));

        // Inicia pausa pagada 16:33:35
        $this->travelTo(Carbon::parse('2026-06-05 16:33:35'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $this->paidBreakType->id,
        ]);

        // Finaliza pausa 16:48:42 (15 min reales)
        $this->travelTo(Carbon::parse('2026-06-05 16:48:42'));
        $this->actingAs($this->user)->post(route('time-clock.break.end'));

        // Check-out 21:35:03
        $this->travelTo(Carbon::parse('2026-06-05 21:35:03'));
        $this->actingAs($this->user)->post(route('time-clock.clock-out'));

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->first();
        $break = $this->employee->breaks()->first();

        // La pausa existe y dura 15 min, igual que en el caso no pagado.
        $this->assertSame(15, $break->duration_minutes);

        // gross = 14:10:39 → 21:35:03 = 7h 24m 24s (no cambia).
        $this->assertEqualsWithDelta(7.41, (float) $entry->gross_hours, 0.01);

        // Las pausas PAGADAS no se restan: break_hours = 0.
        $this->assertSame(0.0, (float) $entry->break_hours);

        // net = gross (los 15 min de pausa se pagan, no se descuentan).
        $this->assertEqualsWithDelta(7.41, (float) $entry->net_hours, 0.01);
        $this->assertSame((float) $entry->gross_hours, (float) $entry->net_hours);

        // netRatio = 1.0 → cada segmento se clasifica sin encogerse.
        $this->assertEqualsWithDelta(4.82, (float) $entry->regular_hours, 0.03);
        $this->assertEqualsWithDelta(2.58, (float) $entry->night_hours, 0.03);
        $this->assertSame(0.0, (float) $entry->sunday_holiday_hours);
        $this->assertSame(0.0, (float) $entry->overtime_day_hours);
        $this->assertSame(0.0, (float) $entry->overtime_night_hours);

        // Los 8 buckets suman el neto.
        $bucketsSum = (float) $entry->regular_hours
            + (float) $entry->night_hours
            + (float) $entry->sunday_holiday_hours
            + (float) $entry->night_sunday_hours
            + (float) $entry->overtime_day_hours
            + (float) $entry->overtime_night_hours
            + (float) $entry->overtime_day_sunday_hours
            + (float) $entry->overtime_night_sunday_hours;
        $this->assertEqualsWithDelta((float) $entry->net_hours, $bucketsSum, 0.02);
    }

    public function test_unpaid_break_is_never_negative_nor_inflates_net(): void
    {
        $this->travelTo(Carbon::parse('2026-06-05 14:10:39'));
        $this->actingAs($this->user)->post(route('time-clock.clock-in'));

        $this->travelTo(Carbon::parse('2026-06-05 16:33:35'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $this->unpaidBreakType->id,
        ]);

        $this->travelTo(Carbon::parse('2026-06-05 16:48:42'));
        $this->actingAs($this->user)->post(route('time-clock.break.end'));

        $this->travelTo(Carbon::parse('2026-06-05 21:35:03'));
        $this->actingAs($this->user)->post(route('time-clock.clock-out'));

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->first();
        $break = $this->employee->breaks()->first();

        $this->assertGreaterThan(0, $break->duration_minutes);
        $this->assertGreaterThanOrEqual(0.0, (float) $entry->break_hours);
        $this->assertLessThan((float) $entry->gross_hours, (float) $entry->net_hours);
    }
}
