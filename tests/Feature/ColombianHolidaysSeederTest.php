<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Company\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColombianHolidaysSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string> name => Y-m-d
     */
    private function seededHolidays(): array
    {
        $company = Company::create([
            'name' => 'Seeder Co',
            'slug' => 'seeder-co',
        ]);

        return Holiday::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [
                $holiday->name => $holiday->date->format('Y-m-d'),
            ])
            ->all();
    }

    public function test_it_seeds_the_nineteen_colombian_national_holidays_for_2026(): void
    {
        $holidays = $this->seededHolidays();

        $this->assertCount(19, $holidays);

        $this->assertSame([
            'Año Nuevo' => '2026-01-01',
            'Día del Trabajo' => '2026-05-01',
            'Día de la Independencia' => '2026-07-20',
            'Batalla de Boyacá' => '2026-08-07',
            'Inmaculada Concepción' => '2026-12-08',
            'Navidad' => '2026-12-25',
            'Reyes Magos' => '2026-01-12',
            'San José' => '2026-03-23',
            'Jueves Santo' => '2026-04-02',
            'Viernes Santo' => '2026-04-03',
            'Ascensión del Señor' => '2026-05-18',
            'Corpus Christi' => '2026-06-08',
            'Sagrado Corazón' => '2026-06-15',
            'San Pedro y San Pablo' => '2026-06-29',
            'Virgen de Chiquinquirá' => '2026-07-13',
            'Asunción de la Virgen' => '2026-08-17',
            'Día de la Raza' => '2026-10-12',
            'Todos los Santos' => '2026-11-02',
            'Independencia de Cartagena' => '2026-11-16',
        ], $holidays);
    }

    public function test_it_seeds_the_new_virgen_de_chiquinquira_holiday(): void
    {
        $holidays = $this->seededHolidays();

        $this->assertArrayHasKey('Virgen de Chiquinquirá', $holidays);
        $this->assertSame('2026-07-13', $holidays['Virgen de Chiquinquirá']);
    }
}
