<?php

namespace Database\Factories;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\TimeTracking\Models\TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employee = Employee::factory()->create();

        $date = fake()->dateTimeBetween('-1 month', 'now');
        $clockIn = (clone $date)->setTime(8, 0);
        $clockOut = (clone $date)->setTime(17, 0);

        return [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'date' => $date->format('Y-m-d'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'gross_hours' => 9,
            'break_hours' => 1,
            'net_hours' => 8,
            'status' => 'calculated',
        ];
    }

    /**
     * Turno aún abierto (sin clock_out).
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'clock_out' => null,
            'gross_hours' => 0,
            'break_hours' => 0,
            'net_hours' => 0,
            'status' => 'pending',
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
        ]);
    }
}
