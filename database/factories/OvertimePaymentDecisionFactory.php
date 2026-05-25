<?php

namespace Database\Factories;

use App\Domain\Company\Models\OvertimePaymentDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Company\Models\OvertimePaymentDecision>
 */
class OvertimePaymentDecisionFactory extends Factory
{
    protected $model = OvertimePaymentDecision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '-1 month');
        $end = (clone $start)->modify('+14 days');

        return [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'pay_overtime' => true,
            'exported_at' => now(),
        ];
    }

    public function notPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_overtime' => false,
        ]);
    }

    public function forCompanyReport(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => null,
        ]);
    }
}
