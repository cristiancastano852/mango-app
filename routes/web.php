<?php

use App\Http\Controllers\Admin\ManualCheckInController;
use App\Http\Controllers\Admin\TimeEntryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\TimeClockController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Admin-only routes
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::resource('schedules', SchedulesController::class);
        Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');

        // Admin time entries management
        Route::get('admin/time-entries', [TimeEntryController::class, 'index'])->name('admin.time-entries.index');
        Route::get('admin/time-entries/{timeEntry}/edit', [TimeEntryController::class, 'edit'])->name('admin.time-entries.edit');
        Route::put('admin/time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('admin.time-entries.update');

        // Manual check-in
        Route::post('admin/manual-check-in', [ManualCheckInController::class, 'store'])->name('admin.manual-check-in');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/employee', [ReportController::class, 'employee'])->name('employee');
            Route::get('/company', [ReportController::class, 'company'])->name('company');
            Route::get('/employee/excel', [ReportController::class, 'exportEmployeeExcel'])->name('employee.excel');
            Route::get('/employee/pdf', [ReportController::class, 'exportEmployeePdf'])->name('employee.pdf');
            Route::get('/company/excel', [ReportController::class, 'exportCompanyExcel'])->name('company.excel');
            Route::get('/company/pdf', [ReportController::class, 'exportCompanyPdf'])->name('company.pdf');
        });
    });

    // Time Clock (all authenticated employees)
    Route::get('time-clock', [TimeClockController::class, 'index'])->name('time-clock.index');
    Route::post('time-clock/clock-in', [TimeClockController::class, 'clockIn'])->name('time-clock.clock-in');
    Route::post('time-clock/clock-out', [TimeClockController::class, 'clockOut'])->name('time-clock.clock-out');
    Route::post('time-clock/break/start', [TimeClockController::class, 'startBreak'])->name('time-clock.break.start');
    Route::post('time-clock/break/end', [TimeClockController::class, 'endBreak'])->name('time-clock.break.end');
});

require __DIR__.'/settings.php';
