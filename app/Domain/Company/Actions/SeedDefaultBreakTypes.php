<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\TimeTracking\Models\BreakType;
use Illuminate\Support\Facades\Date;

class SeedDefaultBreakTypes
{
    public function execute(Company $company): void
    {
        $now = Date::now();

        BreakType::insert([
            [
                'company_id' => $company->id,
                'name' => 'Almuerzo',
                'slug' => 'almuerzo',
                'icon' => '🍽️',
                'color' => '#F59E0B',
                'is_paid' => false,
                'max_duration_minutes' => 60,
                'max_per_day' => 1,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'name' => 'Descanso',
                'slug' => 'descanso',
                'icon' => '☕',
                'color' => '#3B82F6',
                'is_paid' => true,
                'max_duration_minutes' => 15,
                'max_per_day' => 2,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'name' => 'Baño',
                'slug' => 'bano',
                'icon' => '🚻',
                'color' => '#8B5CF6',
                'is_paid' => true,
                'max_duration_minutes' => null,
                'max_per_day' => null,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'name' => 'Personal',
                'slug' => 'personal',
                'icon' => '👤',
                'color' => '#EF4444',
                'is_paid' => false,
                'max_duration_minutes' => 30,
                'max_per_day' => 1,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'company_id' => $company->id,
                'name' => 'Médica',
                'slug' => 'medica',
                'icon' => '🏥',
                'color' => '#10B981',
                'is_paid' => true,
                'max_duration_minutes' => null,
                'max_per_day' => null,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
