<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Actions\DeleteEmployeeAdjustment;
use App\Domain\Employee\Actions\SaveEmployeeAdjustment;
use App\Domain\Employee\Models\Employee;
use App\Domain\Employee\Models\EmployeeAdjustment;
use App\Http\Requests\Employee\StoreEmployeeAdjustmentRequest;
use Illuminate\Http\RedirectResponse;

class EmployeeAdjustmentController extends Controller
{
    public function store(StoreEmployeeAdjustmentRequest $request, Employee $employee, SaveEmployeeAdjustment $action): RedirectResponse
    {
        $action->execute($request->validated(), $employee);

        return back()->with('success', __('messages.adjustment_saved'));
    }

    public function update(StoreEmployeeAdjustmentRequest $request, Employee $employee, EmployeeAdjustment $adjustment, SaveEmployeeAdjustment $action): RedirectResponse
    {
        $action->execute($request->validated(), $employee, $adjustment);

        return back()->with('success', __('messages.adjustment_saved'));
    }

    public function destroy(Employee $employee, EmployeeAdjustment $adjustment, DeleteEmployeeAdjustment $action): RedirectResponse
    {
        $action->execute($adjustment);

        return back()->with('success', __('messages.adjustment_deleted'));
    }
}
