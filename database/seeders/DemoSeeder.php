<?php

namespace Database\Seeders;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Position;
use App\Domain\Organization\Models\Schedule;
use App\Domain\TimeTracking\Models\BreakType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin (sin company)
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@mangoapp.co',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        // Company demo
        $company = Company::create([
            'name' => 'Restaurante El Mango',
            'slug' => 'restaurante-el-mango',
            'timezone' => 'America/Bogota',
            'country' => 'CO',
            'settings' => [
                'lunch_duration' => 60,
                'round_minutes' => 5,
            ],
        ]);

        // Admin de la empresa
        $adminUser = User::create([
            'company_id' => $company->id,
            'name' => 'Carlos Admin',
            'email' => 'carlos@elmango.co',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('admin');

        // Location
        $location = Location::create([
            'company_id' => $company->id,
            'name' => 'Sede Principal',
            'address' => 'Cra 7 #45-12, Bogotá',
        ]);

        // Departments
        $cocina = Department::create(['company_id' => $company->id, 'name' => 'Cocina']);
        $servicio = Department::create(['company_id' => $company->id, 'name' => 'Servicio']);
        $admin = Department::create(['company_id' => $company->id, 'name' => 'Administración']);

        // Positions
        $chef = Position::create(['company_id' => $company->id, 'department_id' => $cocina->id, 'name' => 'Chef']);
        $cocinero = Position::create(['company_id' => $company->id, 'department_id' => $cocina->id, 'name' => 'Cocinero']);
        $mesero = Position::create(['company_id' => $company->id, 'department_id' => $servicio->id, 'name' => 'Mesero']);
        $cajero = Position::create(['company_id' => $company->id, 'department_id' => $servicio->id, 'name' => 'Cajero']);

        // Schedules
        $turnoManana = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Turno Mañana',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5, 6],
        ]);

        $turnoTarde = Schedule::create([
            'company_id' => $company->id,
            'name' => 'Turno Tarde',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'break_duration' => 60,
            'days_of_week' => [1, 2, 3, 4, 5, 6],
        ]);

        // Break Types (defaults)
        $breakTypes = [
            ['name' => 'Almuerzo', 'slug' => 'almuerzo', 'icon' => '🍽️', 'color' => '#F59E0B', 'is_paid' => false, 'max_duration_minutes' => 60, 'max_per_day' => 1, 'is_default' => true],
            ['name' => 'Descanso', 'slug' => 'descanso', 'icon' => '☕', 'color' => '#3B82F6', 'is_paid' => true, 'max_duration_minutes' => 15, 'max_per_day' => 2, 'is_default' => true],
            ['name' => 'Baño', 'slug' => 'bano', 'icon' => '🚻', 'color' => '#8B5CF6', 'is_paid' => true, 'max_duration_minutes' => null, 'max_per_day' => null, 'is_default' => true],
            ['name' => 'Personal', 'slug' => 'personal', 'icon' => '👤', 'color' => '#EF4444', 'is_paid' => false, 'max_duration_minutes' => 30, 'max_per_day' => 1, 'is_default' => true],
            ['name' => 'Médica', 'slug' => 'medica', 'icon' => '🏥', 'color' => '#10B981', 'is_paid' => true, 'max_duration_minutes' => null, 'max_per_day' => null, 'is_default' => true],
        ];

        foreach ($breakTypes as $bt) {
            BreakType::create(array_merge($bt, ['company_id' => $company->id]));
        }

        // Employees
        $empleados = [
            ['name' => 'María García', 'email' => 'maria@elmango.co', 'department_id' => $cocina->id, 'position_id' => $chef->id, 'schedule_id' => $turnoManana->id],
            ['name' => 'Juan Pérez', 'email' => 'juan@elmango.co', 'department_id' => $cocina->id, 'position_id' => $cocinero->id, 'schedule_id' => $turnoManana->id],
            ['name' => 'Ana López', 'email' => 'ana@elmango.co', 'department_id' => $servicio->id, 'position_id' => $mesero->id, 'schedule_id' => $turnoTarde->id],
            ['name' => 'Pedro Martínez', 'email' => 'pedro@elmango.co', 'department_id' => $servicio->id, 'position_id' => $mesero->id, 'schedule_id' => $turnoTarde->id],
            ['name' => 'Laura Rodríguez', 'email' => 'laura@elmango.co', 'department_id' => $servicio->id, 'position_id' => $cajero->id, 'schedule_id' => $turnoManana->id],
        ];

        foreach ($empleados as $i => $emp) {
            $user = User::create([
                'company_id' => $company->id,
                'name' => $emp['name'],
                'email' => $emp['email'],
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('employee');

            Employee::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'department_id' => $emp['department_id'],
                'position_id' => $emp['position_id'],
                'employee_code' => 'EMP-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'hire_date' => now()->subMonths(rand(1, 24)),
                'schedule_id' => $emp['schedule_id'],
                'location_id' => $location->id,
            ]);
        }
    }
}
