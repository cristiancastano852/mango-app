<?php

namespace Tests\Feature\Admin;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function entryOn(string $date): TimeEntry
    {
        return TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => $date,
            'clock_in' => $date.' 08:00:00',
            'clock_out' => $date.' 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);
    }

    public function test_index_filters_by_date_range(): void
    {
        $this->entryOn('2026-06-01');
        $this->entryOn('2026-06-10');
        $this->entryOn('2026-06-20');

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index', [
                'date_from' => '2026-06-05',
                'date_to' => '2026-06-15',
            ]));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TimeEntries/Index')
            ->where('entries.total', 1));
    }

    public function test_index_filters_by_employee(): void
    {
        $this->entryOn('2026-06-01');

        $otherUser = User::factory()->create(['company_id' => $this->company->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create(['user_id' => $otherUser->id, 'company_id' => $this->company->id]);
        TimeEntry::create([
            'employee_id' => $otherEmployee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-02',
            'clock_in' => '2026-06-02 08:00:00',
            'clock_out' => '2026-06-02 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index', ['employee_id' => $this->employee->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TimeEntries/Index')
            ->where('entries.total', 1));
    }

    public function test_index_without_filters_shows_all_active(): void
    {
        $this->entryOn('2026-06-01');
        $this->entryOn('2026-06-10');

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TimeEntries/Index')
            ->where('entries.total', 2));
    }

    public function test_index_includes_schedule_hours_and_breaks_detail(): void
    {
        $entry = $this->entryOn('2026-06-01');
        $entry->update(['break_hours' => 1.0]);

        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'icon' => '🍽️',
            'color' => '#FF9800',
            'is_paid' => false,
            'is_active' => true,
        ]);

        BreakEntry::create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $breakType->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 13:00:00',
            'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TimeEntries/Index')
            ->where('entries.data.0.clock_in', Carbon::parse('2026-06-01 08:00:00')->toIso8601String())
            ->where('entries.data.0.clock_out', Carbon::parse('2026-06-01 17:00:00')->toIso8601String())
            ->where('entries.data.0.gross_hours', '9.00')
            ->where('entries.data.0.break_hours', '1.00')
            ->where('entries.data.0.net_hours', '8.00')
            ->has('entries.data.0.breaks', 1)
            ->where('entries.data.0.breaks.0.name', 'Almuerzo')
            ->where('entries.data.0.breaks.0.icon', '🍽️')
            ->where('entries.data.0.breaks.0.color', '#FF9800')
            ->where('entries.data.0.breaks.0.is_paid', false)
            ->where('entries.data.0.breaks.0.duration_minutes', 60)
            ->where('entries.data.0.breaks.0.in_progress', false)
        );
    }

    public function test_index_loads_breaks_without_n_plus_one(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $makeEntriesWithBreaks = function (int $fromDay, int $toDay) use ($breakType): void {
            foreach (range($fromDay, $toDay) as $day) {
                $date = sprintf('2026-06-%02d', $day);
                $entry = $this->entryOn($date);
                BreakEntry::create([
                    'time_entry_id' => $entry->id,
                    'employee_id' => $this->employee->id,
                    'company_id' => $this->company->id,
                    'break_type_id' => $breakType->id,
                    'started_at' => "{$date} 12:00:00",
                    'ended_at' => "{$date} 13:00:00",
                    'duration_minutes' => 60,
                ]);
            }
        };

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->adminUser)
                ->get(route('admin.time-entries.index'))
                ->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $makeEntriesWithBreaks(1, 2);

        // Warm-up: la primera request cachea roles/permisos y haría ruido en el conteo.
        $this->actingAs($this->adminUser)->get(route('admin.time-entries.index'));

        $queriesWithFewEntries = $countQueries();

        $makeEntriesWithBreaks(3, 20);
        $queriesWithFullPage = $countQueries();

        // Eager loading: el número de queries no crece con la cantidad de registros.
        $this->assertEquals($queriesWithFewEntries, $queriesWithFullPage);
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
            'status' => 'edited',
        ]);

        $entry->refresh();
        $this->assertNotNull($entry->edit_reason);
        $this->assertEquals($this->adminUser->id, $entry->edited_by);
        $this->assertEquals('edited', $entry->status);
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

    public function test_admin_can_create_time_entry(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 08:00',
                'clock_out' => '2026-06-01 17:00',
            ]);

        $response->assertRedirect(route('admin.time-entries.index'));
        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'status' => 'edited',
        ]);
    }

    public function test_cannot_create_duplicate_active_entry_same_day(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 09:00',
                'clock_out' => '2026-06-01 18:00',
            ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_database_blocks_two_active_entries_same_day(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'status' => 'pending',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 09:00:00',
            'status' => 'pending',
        ]);
    }

    public function test_can_recreate_entry_after_soft_delete(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);
        $entry->delete();

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 09:00',
                'clock_out' => '2026-06-01 18:00',
            ]);

        $response->assertRedirect(route('admin.time-entries.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_create_rejects_clock_out_before_clock_in(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 17:00',
                'clock_out' => '2026-06-01 08:00',
            ]);

        $response->assertSessionHasErrors('clock_out');
    }

    public function test_employee_cannot_create_time_entry(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');

        $response = $this->actingAs($employeeUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $this->employee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 08:00',
                'clock_out' => '2026-06-01 17:00',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_cannot_create_entry_for_other_company_employee(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.time-entries.store'), [
                'employee_id' => $otherEmployee->id,
                'date' => '2026-06-01',
                'clock_in' => '2026-06-01 08:00',
                'clock_out' => '2026-06-01 17:00',
            ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_update_recalculates_hours(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 9,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => '2026-06-01 08:00',
                'clock_out' => '2026-06-01 16:00',
                'edit_reason' => 'Ajuste de salida',
            ]);

        $response->assertRedirect(route('admin.time-entries.index'));
        $entry->refresh();
        $this->assertEquals(8.0, (float) $entry->gross_hours);
        $this->assertEquals(8.0, (float) $entry->net_hours);
        $this->assertEquals('edited', $entry->status);
    }

    public function test_update_rejects_when_break_falls_outside_new_range(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $breakType = BreakType::factory()->create(['company_id' => $this->company->id]);
        BreakEntry::create([
            'time_entry_id' => $entry->id,
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $breakType->id,
            'started_at' => '2026-06-01 12:00:00',
            'ended_at' => '2026-06-01 13:00:00',
            'duration_minutes' => 60,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => '2026-06-01 14:00',
                'clock_out' => '2026-06-01 17:00',
                'edit_reason' => 'Ajuste que deja la pausa fuera',
            ]);

        $response->assertSessionHasErrors('clock_in');
    }

    public function test_admin_cannot_update_other_company_entry(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser->assignRole('employee');
        $otherEmployee = Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
        ]);
        $entry = TimeEntry::withoutGlobalScopes()->create([
            'employee_id' => $otherEmployee->id,
            'company_id' => $otherCompany->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->put(route('admin.time-entries.update', $entry), [
                'clock_in' => '2026-06-01 09:00',
                'clock_out' => '2026-06-01 18:00',
                'edit_reason' => 'Intento cross-company',
            ]);

        $response->assertNotFound();
    }

    public function test_admin_can_soft_delete_entry(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('admin.time-entries.destroy', $entry));

        $response->assertRedirect(route('admin.time-entries.index'));
        $this->assertSoftDeleted('time_entries', ['id' => $entry->id]);
    }

    public function test_soft_deleted_entry_is_excluded_from_index(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);
        $entry->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.time-entries.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TimeEntries/Index')
            ->where('entries.total', 0));
    }

    public function test_employee_cannot_delete_entry(): void
    {
        $employeeUser = User::factory()->create(['company_id' => $this->company->id]);
        $employeeUser->assignRole('employee');
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => '2026-06-01',
            'clock_in' => '2026-06-01 08:00:00',
            'clock_out' => '2026-06-01 17:00:00',
            'gross_hours' => 9,
            'net_hours' => 8,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($employeeUser)
            ->delete(route('admin.time-entries.destroy', $entry));

        $response->assertForbidden();
    }
}
