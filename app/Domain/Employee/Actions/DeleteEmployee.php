<?php

namespace App\Domain\Employee\Actions;

use App\Domain\Employee\Models\Employee;
use Illuminate\Support\Facades\DB;

class DeleteEmployee
{
    public function execute(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $employee->user->update(['is_active' => false]);
            $employee->delete();
        });
    }
}
