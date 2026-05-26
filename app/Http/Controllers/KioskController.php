<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Models\Employee;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\TimeTracking\Actions\ClockIn;
use App\Domain\TimeTracking\Actions\ClockOut;
use App\Domain\TimeTracking\Actions\EndBreak;
use App\Domain\TimeTracking\Actions\StartBreak;
use App\Domain\TimeTracking\Models\BreakType;
use App\Domain\TimeTracking\Models\TimeEntry;
use App\Http\Requests\KioskLookupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(): Response
    {
        $company = $this->tenant->get();
        $kioskEmployee = null;
        $todayEntry = null;
        $breakTypes = collect();
        $kioskAction = session()->pull('kiosk_action');

        $employeeId = session('kiosk_employee_id');
        $companyId = session('kiosk_company_id');

        if ($employeeId && $companyId === $company->id) {
            $employee = Employee::withoutGlobalScopes()
                ->where('id', $employeeId)
                ->where('company_id', $company->id)
                ->with('user')
                ->first();

            if ($employee) {
                $kioskEmployee = ['id' => $employee->id, 'name' => $employee->user->name];

                $todayEntry = TimeEntry::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('date', now()->toDateString())
                    ->with(['breaks.breakType'])
                    ->first();

                $breakTypes = BreakType::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('is_active', true)
                    ->get();
            }
        }

        return Inertia::render('Kiosk/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug],
            'kioskEmployee' => $kioskEmployee,
            'todayEntry' => $todayEntry,
            'breakTypes' => $breakTypes,
            'kioskAction' => $kioskAction,
        ]);
    }

    public function lookup(KioskLookupRequest $request): RedirectResponse
    {
        $company = $this->tenant->get();

        $employee = Employee::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->where('document_number', $request->document_number)
            ->with('user')
            ->first();

        if (! $employee) {
            return back()->withErrors(['document_number' => __('messages.kiosk_employee_not_found')]);
        }

        session([
            'kiosk_employee_id' => $employee->id,
            'kiosk_company_id' => $company->id,
        ]);

        return redirect()->route('kiosk.index');
    }

    public function clockIn(Request $request, ClockIn $action): RedirectResponse
    {
        $employee = $this->resolveKioskEmployee($request);

        try {
            $action->execute($employee);
        } catch (ValidationException) {
            session()->forget(['kiosk_employee_id', 'kiosk_company_id']);

            return redirect()->route('kiosk.index');
        }

        session()->forget(['kiosk_employee_id', 'kiosk_company_id']);
        session(['kiosk_action' => [
            'type' => 'clock_in',
            'time' => now()->format('g:i a'),
            'name' => $employee->user->name,
        ]]);

        return redirect()->route('kiosk.index');
    }

    public function clockOut(Request $request, ClockOut $action): RedirectResponse
    {
        $employee = $this->resolveKioskEmployee($request);

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        try {
            $action->execute($entry);
        } catch (ValidationException) {
            session()->forget(['kiosk_employee_id', 'kiosk_company_id']);

            return redirect()->route('kiosk.index');
        }

        session()->forget(['kiosk_employee_id', 'kiosk_company_id']);
        session(['kiosk_action' => [
            'type' => 'clock_out',
            'time' => now()->format('g:i a'),
            'name' => $employee->user->name,
        ]]);

        return redirect()->route('kiosk.index');
    }

    public function startBreak(Request $request, StartBreak $action): RedirectResponse
    {
        $company = $this->tenant->get();

        $request->validate([
            'break_type_id' => [
                'required',
                Rule::exists('break_types', 'id')->where('company_id', $company->id)->where('is_active', true),
            ],
        ]);

        $employee = $this->resolveKioskEmployee($request);

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        try {
            $action->execute($entry, $request->input('break_type_id'));
        } catch (ValidationException) {
            session()->forget(['kiosk_employee_id', 'kiosk_company_id']);

            return redirect()->route('kiosk.index');
        }

        session()->forget(['kiosk_employee_id', 'kiosk_company_id']);
        session(['kiosk_action' => [
            'type' => 'break_start',
            'time' => now()->format('g:i a'),
            'name' => $employee->user->name,
        ]]);

        return redirect()->route('kiosk.index');
    }

    public function endBreak(Request $request, EndBreak $action): RedirectResponse
    {
        $employee = $this->resolveKioskEmployee($request);

        $entry = TimeEntry::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('date', now()->toDateString())
            ->firstOrFail();

        $activeBreak = $entry->breaks()->whereNull('ended_at')->firstOrFail();

        try {
            $action->execute($activeBreak);
        } catch (ValidationException) {
            session()->forget(['kiosk_employee_id', 'kiosk_company_id']);

            return redirect()->route('kiosk.index');
        }

        session()->forget(['kiosk_employee_id', 'kiosk_company_id']);
        session(['kiosk_action' => [
            'type' => 'break_end',
            'time' => now()->format('g:i a'),
            'name' => $employee->user->name,
        ]]);

        return redirect()->route('kiosk.index');
    }

    public function reset(): RedirectResponse
    {
        session()->forget(['kiosk_employee_id', 'kiosk_company_id', 'kiosk_action']);

        return redirect()->route('kiosk.index');
    }

    private function resolveKioskEmployee(Request $request): Employee
    {
        $employeeId = session('kiosk_employee_id');
        $companyId = session('kiosk_company_id');

        abort_if(! $employeeId || $companyId !== $this->tenant->get()->id, 403);

        return Employee::withoutGlobalScopes()
            ->where('id', $employeeId)
            ->where('company_id', $this->tenant->get()->id)
            ->with('user')
            ->firstOrFail();
    }
}
