<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchedulesControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $adminUser;

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
    }

    public function test_admin_can_view_schedules_index(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('schedules.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Schedules/Index'));
    }

    public function test_non_admin_cannot_view_schedules(): void
    {
        $regularUser = User::factory()->create(['company_id' => $this->company->id]);
        $regularUser->assignRole('employee');

        $response = $this->actingAs($regularUser)->get(route('schedules.index'));
        $response->assertForbidden();
    }

    public function test_admin_can_create_schedule(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('schedules.store'), [
            'name' => 'Morning Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'name' => 'Morning Shift',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_schedule_creation_requires_name(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('schedules.store'), [
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_schedule_end_time_must_be_after_start_time(): void
    {
        $response = $this->actingAs($this->adminUser)->post(route('schedules.store'), [
            'name' => 'Bad Schedule',
            'start_time' => '17:00',
            'end_time' => '08:00',
            'break_duration' => 60,
            'days_of_week' => [1],
        ]);

        $response->assertSessionHasErrors('end_time');
    }

    public function test_admin_can_update_schedule(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Old Name',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response = $this->actingAs($this->adminUser)->put(route('schedules.update', $schedule), [
            'name' => 'Updated Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration' => 30,
            'days_of_week' => [1, 2, 3, 4, 5, 6],
        ]);

        $response->assertRedirect(route('schedules.index'));

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Shift',
        ]);
    }

    public function test_admin_can_delete_schedule(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'To Delete',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5],
        ]);

        $response = $this->actingAs($this->adminUser)->delete(route('schedules.destroy', $schedule));

        $response->assertRedirect(route('schedules.index'));
        $this->assertDatabaseMissing('schedules', ['id' => $schedule->id]);
    }
}
