<?php

namespace Tests\Feature\TimeTracking;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\RecalculateTimeEntry;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecalculateTimeEntryTest extends TestCase
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

    private function makeEntry(): TimeEntry
    {
        return TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);
    }

    private function addBreak(TimeEntry $entry, bool $isPaid): BreakEntry
    {
        return $this->addBreakWith($entry, isPaid: $isPaid, durationMinutes: 60);
    }

    private function addBreakWith(
        TimeEntry $entry,
        bool $isPaid,
        int $durationMinutes,
        ?int $maxDurationMinutes = null,
    ): BreakEntry {
        $type = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => $isPaid,
            'max_duration_minutes' => $maxDurationMinutes,
        ]);

        return BreakEntry::factory()->forTimeEntry($entry)->create([
            'break_type_id' => $type->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 12:00:00',
            'duration_minutes' => $durationMinutes,
        ]);
    }

    public function test_recalculates_entry_without_breaks(): void
    {
        $entry = $this->makeEntry();

        app(RecalculateTimeEntry::class)->execute($entry);

        $entry->refresh();
        $this->assertEquals(9.0, (float) $entry->gross_hours);
        $this->assertEquals(0.0, (float) $entry->break_hours);
        $this->assertEquals(9.0, (float) $entry->net_hours);
        $this->assertEquals('edited', $entry->status);
    }

    public function test_unpaid_break_reduces_net_hours(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: false);

        app(RecalculateTimeEntry::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(9.0, (float) $entry->gross_hours);
        $this->assertEquals(1.0, (float) $entry->break_hours);
        $this->assertEquals(8.0, (float) $entry->net_hours);
    }

    public function test_paid_break_does_not_reduce_net_hours(): void
    {
        $entry = $this->makeEntry();
        $this->addBreak($entry, isPaid: true);

        app(RecalculateTimeEntry::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(9.0, (float) $entry->gross_hours);
        $this->assertEquals(0.0, (float) $entry->break_hours);
        $this->assertEquals(9.0, (float) $entry->net_hours);
    }

    public function test_paid_break_with_cap_only_deducts_excess(): void
    {
        $entry = $this->makeEntry();
        $this->addBreakWith($entry, isPaid: true, durationMinutes: 25, maxDurationMinutes: 15);

        app(RecalculateTimeEntry::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(9.0, (float) $entry->gross_hours);
        $this->assertEquals(round(10 / 60, 2), (float) $entry->break_hours);
        $this->assertEquals(round(9 - 10 / 60, 2), (float) $entry->net_hours);
    }

    public function test_paid_break_within_cap_does_not_deduct(): void
    {
        $entry = $this->makeEntry();
        $this->addBreakWith($entry, isPaid: true, durationMinutes: 15, maxDurationMinutes: 15);

        app(RecalculateTimeEntry::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(0.0, (float) $entry->break_hours);
        $this->assertEquals(9.0, (float) $entry->net_hours);
    }

    public function test_combination_of_breaks_accumulates_only_deductible_minutes(): void
    {
        $entry = $this->makeEntry();
        $this->addBreakWith($entry, isPaid: false, durationMinutes: 30);
        $this->addBreakWith($entry, isPaid: true, durationMinutes: 25, maxDurationMinutes: 15);
        $this->addBreakWith($entry, isPaid: true, durationMinutes: 20, maxDurationMinutes: null);

        app(RecalculateTimeEntry::class)->execute($entry->fresh());

        $entry->refresh();
        $this->assertEquals(round(40 / 60, 2), (float) $entry->break_hours);
        $this->assertEquals(round(9 - 40 / 60, 2), (float) $entry->net_hours);
    }

    public function test_open_entry_without_clock_out_is_left_untouched(): void
    {
        $entry = TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => null,
            'gross_hours' => 3,
            'break_hours' => 0,
            'net_hours' => 3,
            'status' => 'pending',
        ]);

        app(RecalculateTimeEntry::class)->execute($entry);

        $entry->refresh();
        $this->assertEquals(3.0, (float) $entry->gross_hours);
        $this->assertEquals(3.0, (float) $entry->net_hours);
        $this->assertEquals('pending', $entry->status);
    }

    public function test_persists_edited_by_and_reason(): void
    {
        $entry = $this->makeEntry();
        $admin = User::factory()->create(['company_id' => $this->company->id]);

        app(RecalculateTimeEntry::class)->execute($entry, $admin, 'Corrección manual');

        $entry->refresh();
        $this->assertEquals($admin->id, $entry->edited_by);
        $this->assertEquals('Corrección manual', $entry->edit_reason);
    }
}
