<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Holiday;
use Illuminate\Database\Seeder;

class ColombianHolidaysSeeder extends Seeder
{
    public function seedForCompany(int $companyId): void
    {
        $year = now()->year;

        $fixed = [
            ['name' => 'Año Nuevo', 'date' => '01-01'],
            ['name' => 'Día del Trabajo', 'date' => '05-01'],
            ['name' => 'Día de la Independencia', 'date' => '07-20'],
            ['name' => 'Batalla de Boyacá', 'date' => '08-07'],
            ['name' => 'Inmaculada Concepción', 'date' => '12-08'],
            ['name' => 'Navidad', 'date' => '12-25'],
        ];

        foreach ($fixed as $holiday) {
            Holiday::create([
                'company_id' => $companyId,
                'name' => $holiday['name'],
                'date' => $year.'-'.$holiday['date'],
                'is_recurring' => true,
                'country' => 'CO',
            ]);
        }

        $mobile = [
            ['name' => 'Reyes Magos', 'date' => $year.'-01-12'],
            ['name' => 'San José', 'date' => $year.'-03-23'],
            ['name' => 'Jueves Santo', 'date' => $year.'-04-02'],
            ['name' => 'Viernes Santo', 'date' => $year.'-04-03'],
            ['name' => 'Ascensión del Señor', 'date' => $year.'-05-25'],
            ['name' => 'Corpus Christi', 'date' => $year.'-06-15'],
            ['name' => 'Sagrado Corazón', 'date' => $year.'-06-22'],
            ['name' => 'San Pedro y San Pablo', 'date' => $year.'-07-06'],
            ['name' => 'Asunción de la Virgen', 'date' => $year.'-08-17'],
            ['name' => 'Día de la Raza', 'date' => $year.'-10-12'],
            ['name' => 'Todos los Santos', 'date' => $year.'-11-02'],
            ['name' => 'Independencia de Cartagena', 'date' => $year.'-11-16'],
        ];

        foreach ($mobile as $holiday) {
            Holiday::create([
                'company_id' => $companyId,
                'name' => $holiday['name'],
                'date' => $holiday['date'],
                'is_recurring' => false,
                'country' => 'CO',
            ]);
        }
    }
}
