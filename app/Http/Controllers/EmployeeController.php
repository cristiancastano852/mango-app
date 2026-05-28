<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Actions\CreateEmployee;
use App\Domain\Employee\Actions\DeleteEmployee;
use App\Domain\Employee\Actions\UpdateEmployee;
use App\Domain\Employee\Models\Employee;
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore imports when re-enabling.
// use App\Domain\Organization\Models\Department;
// LOCATIONS FEATURE DISABLED — Location model exists but is hidden from users. Restore import when re-enabling.
// use App\Domain\Organization\Models\Location;
// DEPARTMENTS & POSITIONS FEATURE DISABLED — restore import when re-enabling.
// use App\Domain\Organization\Models\Position;
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
        // LOCATIONS FEATURE DISABLED — add 'location' back to this eager load when re-enabling.
        // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore 'department', 'position' to eager load when re-enabling.
        $employees = Employee::with(['user'])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"));
            })
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore department filter when re-enabling.
            // ->when($validated['department'] ?? null, fn ($q, $dept) => $q->where('department_id', $dept))
            ->when($validated['status'] ?? null, function ($q, $status) {
                $q->whereHas('user', fn ($u) => $u->where('is_active', $status === 'active'));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore departments prop when re-enabling.
            // 'departments' => Department::select('id', 'name')->get(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Create', [
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these props when re-enabling.
            // 'departments' => Department::select('id', 'name')->get(),
            // 'positions' => Position::select('id', 'name', 'department_id')->get(),
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
        // LOCATIONS FEATURE DISABLED — add 'location' back to this load when re-enabling.
        // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore 'department', 'position' to load when re-enabling.
        $employee->load(['user']);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee): Response
    {
        // TODO: Schedules feature temporarily disabled — restore 'schedule' to load + schedules prop when resuming
        // LOCATIONS FEATURE DISABLED — add 'location' back to this load and restore the locations prop when re-enabling.
        // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore 'department', 'position' to load when re-enabling.
        $employee->load(['user']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            // DEPARTMENTS & POSITIONS FEATURE DISABLED — restore these props when re-enabling.
            // 'departments' => Department::select('id', 'name')->get(),
            // 'positions' => Position::select('id', 'name', 'department_id')->get(),
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
