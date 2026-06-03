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
        $type = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => $isPaid,
        ]);

        return BreakEntry::factory()->forTimeEntry($entry)->create([
            'break_type_id' => $type->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 13:00:00',
            'duration_minutes' => 60,
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
