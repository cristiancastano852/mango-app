<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimeClockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
    }

    public function test_employee_can_view_time_clock(): void
    {
        $response = $this->actingAs($this->user)->get(route('time-clock.index'));
        $response->assertOk();
    }

    public function test_employee_can_clock_in(): void
    {
        $response = $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $response->assertRedirect();

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
        ]);
    }

    public function test_employee_cannot_clock_in_twice(): void
    {
        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $response = $this->actingAs($this->user)->post(route('time-clock.clock-in'));

        $response->assertSessionHasErrors('clock_in');
    }

    public function test_employee_can_clock_out(): void
    {
        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $response = $this->actingAs($this->user)->post(route('time-clock.clock-out'));

        $response->assertRedirect();

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->first();

        $this->assertNotNull($entry->clock_out);
        $this->assertGreaterThanOrEqual(0, (float) $entry->net_hours);
    }

    public function test_employee_can_start_break(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $response = $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('breaks', [
            'employee_id' => $this->employee->id,
            'break_type_id' => $breakType->id,
        ]);
    }

    public function test_employee_can_end_break(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);
        $response = $this->actingAs($this->user)->post(route('time-clock.break.end'));

        $response->assertRedirect();

        $break = $this->employee->breaks()->first();
        $this->assertNotNull($break->ended_at);
        $this->assertNotNull($break->duration_minutes);
    }

    public function test_employee_cannot_start_break_without_clock_in(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);

        $response->assertStatus(404);
    }

    public function test_employee_cannot_start_two_breaks(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);

        $response->assertSessionHasErrors('break');
    }

    public function test_break_limit_per_day_is_enforced(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Descanso',
            'slug' => 'descanso',
            'is_paid' => true,
            'is_active' => true,
            'max_per_day' => 1,
        ]);

        $this->actingAs($this->user)->post(route('time-clock.clock-in'));

        // Primera pausa OK
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);
        $this->actingAs($this->user)->post(route('time-clock.break.end'));

        // Segunda pausa debe fallar
        $response = $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);

        $response->assertSessionHasErrors('break');
    }

    public function test_clock_out_ends_active_break(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)->post(route('time-clock.clock-in'));
        $this->actingAs($this->user)->post(route('time-clock.break.start'), [
            'break_type_id' => $breakType->id,
        ]);
        $this->actingAs($this->user)->post(route('time-clock.clock-out'));

        $break = $this->employee->breaks()->first();
        $this->assertNotNull($break->ended_at);
    }
}
