<?php

namespace Database\Factories;

use App\Domain\Employee\Enums\AdjustmentType;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Employee\Models\EmployeeAdjustment>
 */
class EmployeeAdjustmentFactory extends Factory
{
    protected $model = EmployeeAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory()->create();

        return [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'type' => fake()->randomElement(AdjustmentType::cases()),
            'amount' => fake()->numberBetween(10_000, 500_000),
            'concept' => fake()->randomElement(['Préstamo', 'Adelanto', 'Bono productividad', 'Bonificación']),
            'note' => null,
            'created_by' => null,
        ];
    }

    public function bonus(): static
    {
        return $this->state(fn () => ['type' => AdjustmentType::Bonus]);
    }

    public function deduction(): static
    {
        return $this->state(fn () => ['type' => AdjustmentType::Deduction]);
    }
}
