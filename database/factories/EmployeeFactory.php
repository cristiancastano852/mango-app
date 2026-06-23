<?php

namespace Database\Factories;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Employee\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => fn () => Company::create([
                'name' => fake()->company(),
                'slug' => Str::random(12),
            ])->id,
            'user_id' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'document_number' => (string) fake()->unique()->numberBetween(10_000_000, 99_999_999),
            'hire_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'hourly_rate' => 10000,
            'monthly_base_salary' => null,
            'salary_type' => 'hourly',
            'receives_transport_allowance' => true,
        ];
    }

    /**
     * Empleado con salario base mensual (modelo colombiano por defecto).
     */
    public function monthly(?float $monthlyBaseSalary = null): static
    {
        $base = $monthlyBaseSalary ?? (float) config('payroll.smlv_monthly');
        $divisor = max((int) config('payroll.hourly_divisor'), 1);

        return $this->state(fn (array $attributes) => [
            'salary_type' => 'monthly',
            'monthly_base_salary' => $base,
            'hourly_rate' => round($base / $divisor, 2),
        ]);
    }

    /**
     * Empleado pagado por hora (cálculo por horas trabajadas).
     */
    public function hourly(float $hourlyRate = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'salary_type' => 'hourly',
            'hourly_rate' => $hourlyRate,
            'monthly_base_salary' => null,
        ]);
    }

    /**
     * Empleado con recargo dominical pagado por día (monto fijo plano por dominical).
     */
    public function dominicalByDay(float $dayValue): static
    {
        return $this->state(fn (array $attributes) => [
            'dominical_payment_mode' => 'day',
            'dominical_day_value' => $dayValue,
        ]);
    }
}
