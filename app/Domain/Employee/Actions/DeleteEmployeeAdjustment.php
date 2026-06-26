<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Models\EmployeeAdjustment;

class DeleteEmployeeAdjustment
{
    public function execute(EmployeeAdjustment $adjustment): void
    {
        $adjustment->delete();
    }
}
