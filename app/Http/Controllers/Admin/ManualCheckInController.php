<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Actions\AdminClockIn;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualCheckInController extends Controller
{
    public function store(Request $request, AdminClockIn $action): RedirectResponse
    {
        $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $request->user()->company_id),
            ],
        ]);

        $employee = Employee::findOrFail($request->input('employee_id'));

        $action->execute($employee);

        return back()->with('success', __('messages.manual_check_in'));
    }
}
