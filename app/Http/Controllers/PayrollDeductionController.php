<?php

namespace App\Http\Controllers;

use App\Domain\TimeTracking\Actions\CreatePayrollDeduction;
use App\Domain\TimeTracking\Actions\DeletePayrollDeduction;
use App\Domain\TimeTracking\Models\PayrollDeduction;
use App\Http\Requests\StorePayrollDeductionRequest;
use Illuminate\Http\RedirectResponse;

class PayrollDeductionController extends Controller
{
    public function store(StorePayrollDeductionRequest $request, CreatePayrollDeduction $createDeduction): RedirectResponse
    {
        $createDeduction->execute($request->validated(), $request->user()?->id);

        return back();
    }

    public function destroy(PayrollDeduction $payrollDeduction, DeletePayrollDeduction $deleteDeduction): RedirectResponse
    {
        $deleteDeduction->execute($payrollDeduction);

        return back();
    }
}
