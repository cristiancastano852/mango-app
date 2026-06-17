<?php

namespace Tests\Feature\Admin;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimeEntryBreakControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $employee;

    private TimeEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create(['name' => 'Test Company', 'slug' => 'test-company']);

        $this->adminUser = User::factory()->create(['company_id' => $this->company->id]);
        $this->adminUser->assignRole('admin');

        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);

        $this->entry = TimeEntry::factory()->forEmployee($this->employee)->create([
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'break_hours' => 0,
            'net_hours' => 9,
            'status' => 'calculated',
        ]);
    }

    private function breakType(bool $isPaid = false): BreakType
    {
        return BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => $isPaid,
        ]);
    }

    private function makeBreak(BreakType $type): BreakEntry
    {
        return BreakEntry::factory()->forTimeEntry($this->entry)->create([
            'break_type_id' => $type->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 13:00:00',
            'duration_minutes' => 60,
        ]);
    }

    public function test_admin_can_add_unpaid_break_and_recalculates(): void
    {
        $type = $this->breakType(isPaid: false);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.breaks.store', $this->entry), [
                'break_type_id' => $type->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 13:00',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('breaks', [
            'time_entry_id' => $this->entry->id,
            'duration_minutes' => 60,
        ]);

        $this->entry->refresh();
        $this->assertEquals(1.0, (float) $this->entry->break_hours);
        $this->assertEquals(8.0, (float) $this->entry->net_hours);
        $this->assertEquals('edited', $this->entry->status);
    }

    public function test_admin_can_edit_break_hours(): void
    {
        $break = $this->makeBreak($this->breakType(isPaid: false));

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.breaks.update', [$this->entry, $break]), [
                'break_type_id' => $break->break_type_id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 12:30',
            ]);

        $response->assertRedirect();
        $break->refresh();
        $this->assertEquals(30, $break->duration_minutes);

        $this->entry->refresh();
        $this->assertEquals(0.5, (float) $this->entry->break_hours);
        $this->assertEquals(8.5, (float) $this->entry->net_hours);
    }

    public function test_changing_break_type_to_paid_recalculates_net(): void
    {
        $break = $this->makeBreak($this->breakType(isPaid: false));
        $paidType = $this->breakType(isPaid: true);

        $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.breaks.update', [$this->entry, $break]), [
                'break_type_id' => $paidType->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 13:00',
            ])->assertRedirect();

        $this->entry->refresh();
        $this->assertEquals(0.0, (float) $this->entry->break_hours);
        $this->assertEquals(9.0, (float) $this->entry->net_hours);
    }

    public function test_editing_paid_break_to_exceed_limit_recomputes_overage(): void
    {
        $paidType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'max_duration_minutes' => 15,
        ]);

        $break = BreakEntry::factory()->forTimeEntry($this->entry)->create([
            'break_type_id' => $paidType->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 12:10:00',
            'duration_minutes' => 10,
        ]);

        $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.breaks.update', [$this->entry, $break]), [
                'break_type_id' => $paidType->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 12:30',
            ])->assertRedirect();

        $this->entry->refresh();
        // 30 min - 15 límite = 15 min de exceso = 0.25h
        $this->assertEqualsWithDelta(0.25, (float) $this->entry->paid_break_overage_hours, 0.001);
        $this->assertEqualsWithDelta(8.75, (float) $this->entry->net_hours, 0.001);
    }

    public function test_changing_type_to_limited_paid_deducts_overage(): void
    {
        $break = $this->makeBreak($this->breakType(isPaid: false)); // 60 min no pagada
        $limitedPaid = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'max_duration_minutes' => 15,
        ]);

        $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.breaks.update', [$this->entry, $break]), [
                'break_type_id' => $limitedPaid->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 13:00',
            ])->assertRedirect();

        $this->entry->refresh();
        $this->assertEquals(0.0, (float) $this->entry->break_hours);
        // 60 min - 15 límite = 45 min de exceso = 0.75h
        $this->assertEqualsWithDelta(0.75, (float) $this->entry->paid_break_overage_hours, 0.001);
        $this->assertEqualsWithDelta(8.25, (float) $this->entry->net_hours, 0.001);
    }

    public function test_adding_paid_break_over_limit_deducts_only_overage(): void
    {
        $paidType = BreakType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'max_duration_minutes' => 15,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.breaks.store', $this->entry), [
                'break_type_id' => $paidType->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 12:25',
            ])->assertRedirect();

        $this->entry->refresh();
        $this->assertEquals(0.0, (float) $this->entry->break_hours);
        $this->assertEqualsWithDelta(0.17, (float) $this->entry->paid_break_overage_hours, 0.001);
        $this->assertEqualsWithDelta(8.83, (float) $this->entry->net_hours, 0.001);
    }

    public function test_admin_can_delete_break(): void
    {
        $break = $this->makeBreak($this->breakType(isPaid: false));
        $this->entry->update(['break_hours' => 1, 'net_hours' => 8]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.time-entries.breaks.destroy', [$this->entry, $break]));

        $response->assertRedirect();
        $this->assertDatabaseMissing('breaks', ['id' => $break->id]);

        $this->entry->refresh();
        $this->assertEquals(0.0, (float) $this->entry->break_hours);
        $this->assertEquals(9.0, (float) $this->entry->net_hours);
    }

    public function test_break_outside_shift_range_is_rejected(): void
    {
        $type = $this->breakType(isPaid: false);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.breaks.store', $this->entry), [
                'break_type_id' => $type->id,
                'started_at' => '2026-06-01 07:00',
                'ended_at' => '2026-06-01 07:30',
            ]);

        $response->assertSessionHasErrors('started_at');
    }

    public function test_break_type_from_other_company_is_rejected(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other']);
        $otherType = BreakType::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.breaks.store', $this->entry), [
                'break_type_id' => $otherType->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 13:00',
            ]);

        $response->assertSessionHasErrors('break_type_id');
    }

    public function test_employee_cannot_manage_breaks(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $type = $this->breakType(isPaid: false);

        $response = $this->actingAs($employeeUser)
            ->post(route('admin.time-entries.breaks.store', $this->entry), [
                'break_type_id' => $type->id,
                'started_at' => '2026-06-01 12:00',
                'ended_at' => '2026-06-01 13:00',
            ]);

        $response->assertForbidden();
    }
}
