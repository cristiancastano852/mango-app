<?php

namespace Database\Factories;

use App\Domain\TimeTracking\Models\BreakEntry;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\TimeTracking\Models\BreakEntry>
 */
class BreakEntryFactory extends Factory
{
    protected $model = BreakEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeEntry = TimeEntry::factory()->create();

        $date = Carbon::parse($timeEntry->date);
        $started = $date->copy()->setTime(12, 0);
        $ended = $date->copy()->setTime(13, 0);

        return [
            'time_entry_id' => $timeEntry->id,
            'employee_id' => $timeEntry->employee_id,
            'company_id' => $timeEntry->company_id,
            'break_type_id' => BreakType::factory()->create([
                'company_id' => $timeEntry->company_id,
            ])->id,
            'started_at' => $started,
            'ended_at' => $ended,
            'duration_minutes' => 60,
        ];
    }

    public function forTimeEntry(TimeEntry $timeEntry): static
    {
        return $this->state(fn (array $attributes) => [
            'time_entry_id' => $timeEntry->id,
            'employee_id' => $timeEntry->employee_id,
            'company_id' => $timeEntry->company_id,
        ]);
    }
}
