<?php

namespace Database\Factories;

use App\Domain\TimeTracking\Enums\PayrollDeductionReason;
use App\Domain\TimeTracking\Models\PayrollDeduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\TimeTracking\Models\PayrollDeduction>
 */
class PayrollDeductionFactory extends Factory
{
    protected $model = PayrollDeduction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_date' => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'days' => fake()->randomElement([0.5, 1, 1, 2, 3]),
            'reason' => PayrollDeductionReason::FaltaInjustificada->value,
            'notes' => null,
        ];
    }

    public function reason(PayrollDeductionReason $reason): static
    {
        return $this->state(fn (array $attributes) => [
            'reason' => $reason->value,
        ]);
    }

    public function unjustifiedAbsence(): static
    {
        return $this->reason(PayrollDeductionReason::FaltaInjustificada);
    }

    public function unpaidLeave(): static
    {
        return $this->reason(PayrollDeductionReason::LicenciaNoRemunerada);
    }

    public function days(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'days' => $days,
        ]);
    }
}
