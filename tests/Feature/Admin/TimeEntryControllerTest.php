<?php

namespace Tests\Feature\Admin;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimeEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'super-admin']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $this->adminUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->adminUser->assignRole('admin');

        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $this->employee = Employee::create([
            'user_id' => $employeeUser->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_admin_can_view_time_entries_index(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/TimeEntries/Index'));
    }

    public function test_non_admin_cannot_access_time_entries(): void
    {
        $regularUser = User::factory()->create(['company_id' => $this->company->id]);
        $regularUser->assignRole('employee');

        $response = $this->actingAs($regularUser)
            ->get(route('admin.time-entries.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_edit_form(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.edit', $entry));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/TimeEntries/Edit'));
    }

    public function test_admin_can_update_time_entry(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9,
            'break_hours' => 1,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $newClockIn = Carbon::now()->setTime(9, 0)->format('Y-m-d\TH:i');
        $newClockOut = Carbon::now()->setTime(18, 0)->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => $newClockIn,
                'clock_out' => $newClockOut,
                'edit_reason' => 'Employee reported incorrect clock-in time',
            ]);

        $response->assertRedirect(route('admin.time-entries.index'));

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'edited_by' => $this->adminUser->id,
        ]);

        $entry->refresh();
        $this->assertNotNull($entry->edit_reason);
        $this->assertEquals($this->adminUser->id, $entry->edited_by);
    }

    public function test_update_requires_edit_reason(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => now()->setTime(9, 0)->format('Y-m-d\TH:i'),
                'clock_out' => now()->setTime(18, 0)->format('Y-m-d\TH:i'),
                'edit_reason' => '',
            ]);

        $response->assertSessionHasErrors('edit_reason');
    }

    public function test_clock_out_must_be_after_clock_in(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->setTime(8, 0),
            'clock_out' => now()->setTime(17, 0),
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => now()->setTime(17, 0)->format('Y-m-d\TH:i'),
                'clock_out' => now()->setTime(8, 0)->format('Y-m-d\TH:i'),
                'edit_reason' => 'Test',
            ]);

        $response->assertSessionHasErrors('clock_out');
    }
}
