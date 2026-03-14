<?php

namespace Database\Factories;

use App\Domain\TimeTracking\Models\BreakType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\TimeTracking\Models\BreakType>
 */
class BreakTypeFactory extends Factory
{
    protected $model = BreakType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_paid' => false,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => true,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_paid' => false,
        ]);
    }

    public function lunchDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_paid' => false,
            'is_default' => true,
            'max_duration_minutes' => 60,
            'max_per_day' => 1,
        ]);
    }
}
