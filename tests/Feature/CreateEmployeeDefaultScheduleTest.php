<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Actions\CreateEmployee;
use App\Domain\Organization\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateEmployeeDefaultScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
        ]);
    }

    public function test_employee_inherits_default_schedule_when_none_provided(): void
    {
        $schedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Default',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $this->company->update([
            'settings' => ['default_schedule_id' => $schedule->id],
        ]);

        $action = new CreateEmployee;
        $employee = $action->execute([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], $this->company->id);

        $this->assertEquals($schedule->id, $employee->schedule_id);
    }

    public function test_employee_uses_explicit_schedule_over_default(): void
    {
        $defaultSchedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Default',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $explicitSchedule = Schedule::create([
            'company_id' => $this->company->id,
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '06:00',
        ]);

        $this->company->update([
            'settings' => ['default_schedule_id' => $defaultSchedule->id],
        ]);

        $action = new CreateEmployee;
        $employee = $action->execute([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'schedule_id' => $explicitSchedule->id,
        ], $this->company->id);

        $this->assertEquals($explicitSchedule->id, $employee->schedule_id);
    }

    public function test_employee_gets_null_schedule_when_no_default_configured(): void
    {
        $action = new CreateEmployee;
        $employee = $action->execute([
            'name' => 'Bob Doe',
            'email' => 'bob@example.com',
        ], $this->company->id);

        $this->assertNull($employee->schedule_id);
    }
}
