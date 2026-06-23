<?php

namespace Tests\Feature\TimeTracking;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\ClockOut;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaidBreakOverageTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    private function makeEntry(?string $clockOut = '2026-06-01 17:00:00'): TimeEntry
    {
        return TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => $clockOut,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);
    }

    private function addBreak(TimeEntry $entry, bool $isPaid, ?int $maxDuration, int $durationMinutes, ?string $endedAt = '2026-06-01 12:25:00'): BreakEntry
    {
        $type = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => $isPaid,
            'max_duration_minutes' => $maxDuration,
        ]);

        return BreakEntry::factory()->forTimeEntry($entry)->create([
            'break_type_id' => $type->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function test_paid_break_over_limit_returns_overage(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true, maxDuration: 15, durationMinutes: 25);

        $this->assertEqualsWithDelta(0.17, $entry->paidBreakOverageHours(), 0.001);
    }

    public function test_paid_break_within_limit_returns_zero(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true, maxDuration: 15, durationMinutes: 15);

        $this->assertEquals(0.0, $entry->paidBreakOverageHours());
    }

    public function test_paid_break_without_limit_returns_zero(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true, maxDuration: null, durationMinutes: 40);

        $this->assertEquals(0.0, $entry->paidBreakOverageHours());
    }

    public function test_unpaid_break_over_limit_returns_zero(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: false, maxDuration: 15, durationMinutes: 60);

        $this->assertEquals(0.0, $entry->paidBreakOverageHours());
    }

    public function test_in_progress_paid_break_returns_zero(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true, maxDuration: 15, durationMinutes: 25, endedAt: null);

        $this->assertEquals(0.0, $entry->paidBreakOverageHours());
    }

    public function test_multiple_overages_are_summed(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true, maxDuration: 15, durationMinutes: 25);
        $this->addBreak($entry, isPaid: true, maxDuration: 15, durationMinutes: 20);

        // 10 + 5 = 15 minutos = 0.25h
        $this->assertEqualsWithDelta(0.25, $entry->paidBreakOverageHours(), 0.001);
    }

    public function test_clock_out_deducts_paid_break_overage_from_net_hours(): void
    {
        // Escenario objetivo: 12:00 → 20:00 (8h), pausa pagada 14:00–14:25 con límite 15 min.
        $this->travelTo(Carbon::parse('2026-06-01 20:00:00'));

        $entry = TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 12:00:00',
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);

        $paidType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'max_duration_minutes' => 15,
        ]);

        BreakEntry::factory()->forTimeEntry($entry)->create([
            'break_type_id' => $paidType->id,
            'started_at' => '2026-06-01 14:00:00',
            'ended_at' => '2026-06-01 14:25:00',
            'duration_minutes' => 25,
        ]);

        app(ClockOut::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(8.0, (float) $entry->gross_hours);
        $this->assertEquals(0.0, (float) $entry->break_hours);
        $this->assertEqualsWithDelta(0.17, (float) $entry->paid_break_overage_hours, 0.001);
        $this->assertEqualsWithDelta(7.83, (float) $entry->net_hours, 0.001);

        $this->travelBack();
    }

    public function test_eight_hour_types_sum_equals_net_hours_after_overage(): void
    {
        $this->travelTo(Carbon::parse('2026-06-01 20:00:00'));

        $entry = TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 12:00:00',
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);

        $paidType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'max_duration_minutes' => 15,
        ]);

        BreakEntry::factory()->forTimeEntry($entry)->create([
            'break_type_id' => $paidType->id,
            'started_at' => '2026-06-01 14:00:00',
            'ended_at' => '2026-06-01 14:25:00',
            'duration_minutes' => 25,
        ]);

        app(ClockOut::class)->execute($entry->fresh());

        $entry->refresh();
        $sum = (float) $entry->regular_hours
            + (float) $entry->night_hours
            + (float) $entry->dominical_hours
            + (float) $entry->night_dominical_hours
            + (float) $entry->overtime_day_hours
            + (float) $entry->overtime_night_hours
            + (float) $entry->overtime_day_dominical_hours
            + (float) $entry->overtime_night_dominical_hours;

        $this->assertEqualsWithDelta((float) $entry->net_hours, $sum, 0.01);

        $this->travelBack();
    }
}
