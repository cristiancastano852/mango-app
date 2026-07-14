<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\DayDeductionDecision;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\ResolveDayDeductionDecision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResolveDayDeductionDecisionTest extends TestCase
{
    use RefreshDatabase;

    private ResolveDayDeductionDecision $action;

    private Company $company;

    private Employee $employee;

    private Carbon $start;

    private Carbon $end;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
        ]);

        $user = User::factory()->create(['company_id' => $this->company->id]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'hourly_rate' => 10000,
        ]);

        $this->action = app(ResolveDayDeductionDecision::class);
        $this->start = Carbon::parse('2026-06-01');
        $this->end = Carbon::parse('2026-06-15');
    }

    public function test_request_value_overrides_saved_decision(): void
    {
        DayDeductionDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $this->start->toDateString(),
            'end_date' => $this->end->toDateString(),
            'deducted_days' => 3,
        ]);

        $result = $this->action->execute(
            $this->company->id,
            $this->employee->id,
            $this->start,
            $this->end,
            requestValue: 1,
        );

        $this->assertSame(1, $result);
    }

    public function test_saved_decision_used_when_no_request(): void
    {
        DayDeductionDecision::withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'start_date' => $this->start->toDateString(),
            'end_date' => $this->end->toDateString(),
            'deducted_days' => 3,
        ]);

        $result = $this->action->execute(
            $this->company->id,
            $this->employee->id,
            $this->start,
            $this->end,
        );

        $this->assertSame(3, $result);
    }

    public function test_returns_zero_when_no_request_and_no_saved_decision(): void
    {
        $result = $this->action->execute(
            $this->company->id,
            $this->employee->id,
            $this->start,
            $this->end,
        );

        $this->assertSame(0, $result);
    }

    public function test_negative_request_value_normalized_to_zero(): void
    {
        $result = $this->action->execute(
            $this->company->id,
            $this->employee->id,
            $this->start,
            $this->end,
            requestValue: -2,
        );

        $this->assertSame(0, $result);
    }
}
