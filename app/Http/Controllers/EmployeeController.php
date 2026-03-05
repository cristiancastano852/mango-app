<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Actions\CreateEmployee;
use App\Domain\Employee\Actions\DeleteEmployee;
use App\Domain\Employee\Actions\UpdateEmployee;
use App\Domain\Employee\Models\Employee;
use App\Domain\Organization\Models\Department;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Position;
use App\Domain\Organization\Models\Schedule;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::with(['user', 'department', 'position', 'schedule', 'location'])
            ->when($request->input('search'), function ($q, $search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%"));
            })
            ->when($request->input('department'), fn ($q, $dept) => $q->where('department_id', $dept))
            ->when($request->input('status'), function ($q, $status) {
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
            'schedules' => Schedule::select('id', 'name')->get(),
            'locations' => Location::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): RedirectResponse
    {
        $action->execute($request->validated(), $request->user()->company_id);

        return redirect()->route('employees.index')->with('success', 'Empleado creado exitosamente.');
    }

    public function show(Employee $employee): Response
    {
        $employee->load(['user', 'department', 'position', 'schedule', 'location']);

        return Inertia::render('Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $employee->load(['user', 'department', 'position', 'schedule', 'location']);

        return Inertia::render('Employees/Edit', [
            'employee' => $employee,
            'departments' => Department::select('id', 'name')->get(),
            'positions' => Position::select('id', 'name', 'department_id')->get(),
            'schedules' => Schedule::select('id', 'name')->get(),
            'locations' => Location::select('id', 'name')->get(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $action): RedirectResponse
    {
        $action->execute($employee, $request->validated());

        return redirect()->route('employees.index')->with('success', 'Empleado actualizado exitosamente.');
    }

    public function destroy(Employee $employee, DeleteEmployee $action): RedirectResponse
    {
        $action->execute($employee);

        return redirect()->route('employees.index')->with('success', 'Empleado eliminado exitosamente.');
    }
}
