<?php

use App\Http\Controllers\Admin\ManualCheckInController;
use App\Http\Controllers\Admin\TimeEntryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CompanyRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Onboarding\OnboardingBreakTypesController;
use App\Http\Controllers\Onboarding\OnboardingCompanyController;
use App\Http\Controllers\Onboarding\OnboardingScheduleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\TimeClockController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/pricing', fn () => redirect('/#pricing'))->name('pricing');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/register/company', [CompanyRegistrationController::class, 'create'])->name('register.company.create');
    Route::post('/register/company', [CompanyRegistrationController::class, 'store'])->name('register.company.store');
});

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

    // Tour dismiss
    Route::post('tour/dismiss', [TourController::class, 'dismiss'])->name('tour.dismiss');

    // Onboarding wizard (admin only, not yet completed)
    Route::middleware(['role:admin', 'onboarding'])->prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('company', [OnboardingCompanyController::class, 'show'])->name('company');
        Route::post('company', [OnboardingCompanyController::class, 'update'])->name('company.update');
        Route::get('schedule', [OnboardingScheduleController::class, 'show'])->name('schedule');
        Route::post('schedule', [OnboardingScheduleController::class, 'update'])->name('schedule.update');
        Route::get('break-types', [OnboardingBreakTypesController::class, 'show'])->name('break-types');
        Route::post('break-types', [OnboardingBreakTypesController::class, 'update'])->name('break-types.update');
    });
});

require __DIR__.'/settings.php';
