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

class KioskActionsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    private BreakType $breakType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'document_number' => '123456789',
        ]);

        $this->breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_active' => true,
        ]);
    }

    private function withKioskSession(array $extra = []): static
    {
        return $this->withSession(array_merge([
            'kiosk_employee_id' => $this->employee->id,
            'kiosk_company_id' => $this->company->id,
        ], $extra));
    }

    public function test_clock_in_registers_entry_and_redirects(): void
    {
        $response = $this->withKioskSession()
            ->post(route('kiosk.clock-in', ['company' => $this->company->slug]));

        $response->assertRedirect(route('kiosk.index', ['company' => $this->company->slug]));

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'date' => now()->toDateString(),
        ]);
    }

    public function test_clock_in_clears_kiosk_session(): void
    {
        $this->withKioskSession()
            ->post(route('kiosk.clock-in', ['company' => $this->company->slug]));

        $this->assertNull(session('kiosk_employee_id'));
    }

    public function test_clock_out_registers_exit_and_redirects(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(4),
            'status' => 'clocked_in',
        ]);

        $response = $this->withKioskSession()
            ->post(route('kiosk.clock-out', ['company' => $this->company->slug]));

        $response->assertRedirect(route('kiosk.index', ['company' => $this->company->slug]));

        $this->assertDatabaseHas('time_entries', [
            'employee_id' => $this->employee->id,
            'status' => 'calculated',
        ]);
    }

    public function test_start_break_registers_break(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour(),
            'status' => 'clocked_in',
        ]);

        $response = $this->withKioskSession()
            ->post(route('kiosk.break.start', ['company' => $this->company->slug]), [
                'break_type_id' => $this->breakType->id,
            ]);

        $response->assertRedirect(route('kiosk.index', ['company' => $this->company->slug]));

        $this->assertDatabaseHas('breaks', [
            'employee_id' => $this->employee->id,
            'break_type_id' => $this->breakType->id,
        ]);
    }

    public function test_end_break_closes_active_break(): void
    {
        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(2),
            'status' => 'on_break',
        ]);

        $entry->breaks()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $this->breakType->id,
            'started_at' => now()->subMinutes(15),
        ]);

        $response = $this->withKioskSession()
            ->post(route('kiosk.break.end', ['company' => $this->company->slug]));

        $response->assertRedirect(route('kiosk.index', ['company' => $this->company->slug]));

        $this->assertDatabaseHas('breaks', [
            'employee_id' => $this->employee->id,
            'break_type_id' => $this->breakType->id,
        ]);
        $this->assertNotNull(
            \App\Domain\TimeTracking\Models\BreakEntry::where('employee_id', $this->employee->id)->first()->ended_at
        );
    }

    public function test_action_without_kiosk_session_returns_403(): void
    {
        $response = $this->post(route('kiosk.clock-in', ['company' => $this->company->slug]));

        $response->assertForbidden();
    }

    public function test_action_with_session_from_different_company_returns_403(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-co']);

        $response = $this->withSession([
            'kiosk_employee_id' => $this->employee->id,
            'kiosk_company_id' => $otherCompany->id,
        ])->post(route('kiosk.clock-in', ['company' => $this->company->slug]));

        $response->assertForbidden();
    }

    public function test_start_break_rejects_break_type_from_another_company(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour(),
            'status' => 'clocked_in',
        ]);

        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-co']);
        $foreignBreakType = BreakType::create([
            'company_id' => $otherCompany->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_active' => true,
        ]);

        $response = $this->withKioskSession()
            ->post(route('kiosk.break.start', ['company' => $this->company->slug]), [
                'break_type_id' => $foreignBreakType->id,
            ]);

        $response->assertSessionHasErrors('break_type_id');
        $this->assertDatabaseMissing('breaks', ['employee_id' => $this->employee->id]);
    }

    public function test_start_break_rejects_inactive_break_type(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHour(),
            'status' => 'clocked_in',
        ]);

        $inactiveBreakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Inactivo',
            'slug' => 'inactivo',
            'is_active' => false,
        ]);

        $response = $this->withKioskSession()
            ->post(route('kiosk.break.start', ['company' => $this->company->slug]), [
                'break_type_id' => $inactiveBreakType->id,
            ]);

        $response->assertSessionHasErrors('break_type_id');
        $this->assertDatabaseMissing('breaks', ['employee_id' => $this->employee->id]);
    }
}
