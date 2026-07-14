<?php

namespace Database\Factories;

use App\Domain\Company\Models\DayDeductionDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Company\Models\DayDeductionDecision>
 */
class DayDeductionDecisionFactory extends Factory
{
    protected $model = DayDeductionDecision::class;

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
            'deducted_days' => 0,
            'exported_at' => now(),
        ];
    }

    public function deductedDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'deducted_days' => $days,
        ]);
    }
}
