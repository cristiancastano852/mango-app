<?php

namespace App\Domain\TimeTracking\Actions;

use App\Domain\TimeTracking\Models\PayrollDeduction;

class DeletePayrollDeduction
{
    public function execute(PayrollDeduction $deduction): void
    {
        $deduction->delete();
    }
}
