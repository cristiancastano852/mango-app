<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    // Admin-only routes
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
        Route::resource('schedules', \App\Http\Controllers\SchedulesController::class);
        Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');

        // Admin time entries management
        Route::get('admin/time-entries', [\App\Http\Controllers\Admin\TimeEntryController::class, 'index'])->name('admin.time-entries.index');
        Route::get('admin/time-entries/{timeEntry}/edit', [\App\Http\Controllers\Admin\TimeEntryController::class, 'edit'])->name('admin.time-entries.edit');
        Route::put('admin/time-entries/{timeEntry}', [\App\Http\Controllers\Admin\TimeEntryController::class, 'update'])->name('admin.time-entries.update');

        // Manual check-in
        Route::post('admin/manual-check-in', [\App\Http\Controllers\Admin\ManualCheckInController::class, 'store'])->name('admin.manual-check-in');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\ReportController::class, 'index'])->name('index');
            Route::get('/employee', [\App\Http\Controllers\ReportController::class, 'employee'])->name('employee');
            Route::get('/company', [\App\Http\Controllers\ReportController::class, 'company'])->name('company');
            Route::get('/employee/excel', [\App\Http\Controllers\ReportController::class, 'exportEmployeeExcel'])->name('employee.excel');
            Route::get('/employee/pdf', [\App\Http\Controllers\ReportController::class, 'exportEmployeePdf'])->name('employee.pdf');
            Route::get('/company/excel', [\App\Http\Controllers\ReportController::class, 'exportCompanyExcel'])->name('company.excel');
            Route::get('/company/pdf', [\App\Http\Controllers\ReportController::class, 'exportCompanyPdf'])->name('company.pdf');
        });
    });

    // Time Clock (all authenticated employees)
    Route::get('time-clock', [\App\Http\Controllers\TimeClockController::class, 'index'])->name('time-clock.index');
    Route::post('time-clock/clock-in', [\App\Http\Controllers\TimeClockController::class, 'clockIn'])->name('time-clock.clock-in');
    Route::post('time-clock/clock-out', [\App\Http\Controllers\TimeClockController::class, 'clockOut'])->name('time-clock.clock-out');
    Route::post('time-clock/break/start', [\App\Http\Controllers\TimeClockController::class, 'startBreak'])->name('time-clock.break.start');
    Route::post('time-clock/break/end', [\App\Http\Controllers\TimeClockController::class, 'endBreak'])->name('time-clock.break.end');
});

require __DIR__.'/settings.php';
