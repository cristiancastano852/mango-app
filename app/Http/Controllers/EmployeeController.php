<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Actions\CreateEmployee;
use App\Domain\Employee\Actions\DeleteEmployee;
use App\Domain\Employee\Actions\UpdateEmployee;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
// LOCATIONS FEATURE DISABLED — Location model exists but is hidden from users. Restore import when re-enabling.
// use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Position;
// TODO: Schedules feature temporarily disabled — restore Schedule import when resuming
// use App\Domain\Organization\Models\Schedule;
use App\Http\Requests\Employee\IndexEmployeeRequest;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(IndexEmployeeRequest $request): Response
    {
        $validated = $request->validated();

        // TODO: Schedules feature temporarily disabled — restore 'schedule' to eager load when resuming
        // LOCATIONS FEATURE DISABLED — remove 'location' from eager load when re-enabling to restore it here.
        $employees = Employee::with(['user', 'department', 'position'])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"));
            })
            ->when($validated['department'] ?? null, fn ($q, $dept) => $q->where('department_id', $dept))
            ->when($validated['status'] ?? null, function ($q, $status) {
                $q->whereHas('user', fn ($u) => $u->where('is_active', $status === 'active'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'departments' => Department::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'department', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Create', [
            'departments' => Department::select('id', 'name')->get(),
            'positions' => Position::select('id', 'name', 'department_id')->get(),
            // TODO: Schedules feature temporarily disabled — restore schedules prop when resuming
            // LOCATIONS FEATURE DISABLED — restore this line when re-enabling: 'locations' => Location::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): RedirectResponse
    {
        ['employee' => $employee, 'plain_password' => $plainPassword] = $action->execute($request->validated(), $request->user()->company_id);

        return redirect()->route('employees.show', $employee)
            ->with('success', __('messages.employee_created'))
            ->with('created_password', $plainPassword);
    }

    public function show(Employee $employee): Response
    {
        // TODO: Schedules feature temporarily disabled — restore 'schedule' to load when resuming
        // LOCATIONS FEATURE DISABLED — remove 'location' from load; restore when re-enabling.
        $employee->load(['user', 'department', 'position']);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee): Response
    {
        // TODO: Schedules feature temporarily disabled — restore 'schedule' to load + schedules prop when resuming
        // LOCATIONS FEATURE DISABLED — remove 'location' from load; restore load + prop when re-enabling.
        $employee->load(['user', 'department', 'position']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'departments' => Department::select('id', 'name')->get(),
            'positions' => Position::select('id', 'name', 'department_id')->get(),
            // LOCATIONS FEATURE DISABLED — restore this line when re-enabling: 'locations' => Location::select('id', 'name')->get(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $action): RedirectResponse
    {
        $action->execute($employee, $request->validated());

        return redirect()->route('employees.index')->with('success', __('messages.employee_updated'));
    }

    public function destroy(Employee $employee, DeleteEmployee $action): RedirectResponse
    {
        $action->execute($employee);

        return redirect()->route('employees.index')->with('success', __('messages.employee_deleted'));
    }
}
