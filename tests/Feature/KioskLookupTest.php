<?php

namespace Tests\Feature;

use App\Domain\Company\Models\Company;
use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KioskLookupTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.base_domain' => 'mango-app.test']);

        Role::create(['name' => 'employee']);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
        ]);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $user->assignRole('employee');

        $this->employee = Employee::create([
            'user_id' => $user->id,
            'company_id' => $this->company->id,
            'document_number' => '123456789',
        ]);
    }

    private function tenantUrl(string $routeName, array $parameters = []): string
    {
        $host = $this->company->slug.'.'.config('tenancy.base_domain');

        return 'http://'.$host.route($routeName, $parameters, false);
    }

    public function test_kiosk_index_renders_for_valid_tenant_subdomain(): void
    {
        $response = $this->get($this->tenantUrl('kiosk.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Kiosk/Index')
            ->where('company.slug', $this->company->slug)
            ->where('company.name', $this->company->name)
        );
    }

    public function test_kiosk_index_returns_404_on_apex_domain(): void
    {
        $response = $this->get('http://mango-app.test'.route('kiosk.index', [], false));

        $response->assertNotFound();
    }

    public function test_lookup_finds_employee_by_document_number(): void
    {
        $response = $this->post($this->tenantUrl('kiosk.lookup'), [
            'document_number' => '123456789',
        ]);

        $response->assertRedirect(route('kiosk.index'));

        $this->assertEquals($this->employee->id, session('kiosk_employee_id'));
        $this->assertEquals($this->company->id, session('kiosk_company_id'));
    }

    public function test_lookup_fails_for_nonexistent_document_number(): void
    {
        $response = $this->post($this->tenantUrl('kiosk.lookup'), [
            'document_number' => '999999999',
        ]);

        $response->assertSessionHasErrors('document_number');
        $this->assertNull(session('kiosk_employee_id'));
    }

    public function test_lookup_fails_for_empty_document_number(): void
    {
        $response = $this->post($this->tenantUrl('kiosk.lookup'), [
            'document_number' => '',
        ]);

        $response->assertSessionHasErrors('document_number');
    }

    public function test_lookup_does_not_find_employee_from_another_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other', 'slug' => 'other-co']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id, 'is_active' => true]);
        Employee::create([
            'user_id' => $otherUser->id,
            'company_id' => $otherCompany->id,
            'document_number' => '999999999',
        ]);

        $response = $this->post($this->tenantUrl('kiosk.lookup'), [
            'document_number' => '999999999',
        ]);

        $response->assertSessionHasErrors('document_number');
    }

    public function test_kiosk_index_shows_employee_data_when_session_set(): void
    {
        $response = $this->withSession([
            'kiosk_employee_id' => $this->employee->id,
            'kiosk_company_id' => $this->company->id,
        ])->get($this->tenantUrl('kiosk.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('kioskEmployee.id', $this->employee->id)
        );
    }

    public function test_kiosk_index_shows_today_entry_when_exists(): void
    {
        TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'status' => 'clocked_in',
        ]);

        $response = $this->withSession([
            'kiosk_employee_id' => $this->employee->id,
            'kiosk_company_id' => $this->company->id,
        ])->get($this->tenantUrl('kiosk.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('todayEntry.status', 'clocked_in')
        );
    }

    /**
     * Invariante de seguridad: durante una pausa activa, el payload del kiosco
     * mantiene la jornada abierta (sin clock_out) con una pausa sin cerrar. El
     * frontend deriva de ese contrato el estado `on_break`, que SOLO expone
     * "Finalizar pausa" y nunca "Finalizar jornada". Este test bloquea cualquier
     * cambio futuro que reintroduzca el riesgo de finalizar jornada por error.
     */
    public function test_kiosk_index_on_break_payload_keeps_shift_open_with_unended_break(): void
    {
        $breakType = BreakType::create([
            'company_id' => $this->company->id,
            'name' => 'Almuerzo',
            'slug' => 'almuerzo',
            'is_active' => true,
        ]);

        $entry = TimeEntry::create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(2),
            'status' => 'on_break',
        ]);

        $entry->breaks()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'break_type_id' => $breakType->id,
            'started_at' => now()->subMinutes(20),
        ]);

        $response = $this->withSession([
            'kiosk_employee_id' => $this->employee->id,
            'kiosk_company_id' => $this->company->id,
        ])->get($this->tenantUrl('kiosk.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('todayEntry.status', 'on_break')
            ->where('todayEntry.clock_out', null)
            ->where('todayEntry.breaks.0.ended_at', null)
        );
    }
}
