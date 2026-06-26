<?php

namespace Database\Factories;

use App\Domain\Company\Models\DominicalPaymentDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Company\Models\DominicalPaymentDecision>
 */
class DominicalPaymentDecisionFactory extends Factory
{
    protected $model = DominicalPaymentDecision::class;

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
            'payable_count' => null,
            'exported_at' => now(),
        ];
    }

    public function payableCount(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'payable_count' => $count,
        ]);
    }
}
