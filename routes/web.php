<?php

use App\Http\Controllers\Admin\ManualCheckInController;
use App\Http\Controllers\Admin\TimeEntryController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Onboarding\OnboardingBreakTypesController;
use App\Http\Controllers\Onboarding\OnboardingCompanyController;
use App\Http\Controllers\Onboarding\OnboardingScheduleController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchedulesController;
use App\Http\Controllers\SuperAdmin\CompanyController as SuperAdminCompanyController;
use App\Http\Controllers\TimeClockController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

// TEMPORARY — spike de verificación del Host en serverless. Eliminar tras confirmar (tarea 1.4).
Route::get('__tenant-debug', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'getHost' => $request->getHost(),
        'httpHost' => $request->getHttpHost(),
        'header_host' => $request->header('host'),
        'header_x_forwarded_host' => $request->header('x-forwarded-host'),
        'all_headers' => $request->headers->all(),
    ]);
});

// Public routes
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/pricing', fn () => redirect('/#pricing'))->name('pricing');

// Kiosk (public, no auth required)
Route::prefix('kiosk/{company:slug}')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('index');
    Route::post('/reset', [KioskController::class, 'reset'])->name('reset');
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/lookup', [KioskController::class, 'lookup'])->name('lookup');
        Route::post('/clock-in', [KioskController::class, 'clockIn'])->name('clock-in');
        Route::post('/clock-out', [KioskController::class, 'clockOut'])->name('clock-out');
        Route::post('/break/start', [KioskController::class, 'startBreak'])->name('break.start');
        Route::post('/break/end', [KioskController::class, 'endBreak'])->name('break.end');
    });
});

// Super-admin platform management
Route::middleware(['auth', 'verified', 'role:super-admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('companies', [SuperAdminCompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [SuperAdminCompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [SuperAdminCompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}/edit', [SuperAdminCompanyController::class, 'edit'])->name('companies.edit');
    Route::put('companies/{company}', [SuperAdminCompanyController::class, 'update'])->name('companies.update');
    Route::post('companies/{company}/admin-users', [SuperAdminCompanyController::class, 'storeAdminUser'])->name('companies.admin-users.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Admin-only routes
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('employees', EmployeeController::class);
        // TODO: Schedules feature temporarily disabled (hidden from UI) — remove this comment when resuming
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
        // TODO: Schedules feature temporarily disabled (hidden from UI) — remove this comment when resuming
        Route::get('schedule', [OnboardingScheduleController::class, 'show'])->name('schedule');
        Route::post('schedule', [OnboardingScheduleController::class, 'update'])->name('schedule.update');
        Route::get('break-types', [OnboardingBreakTypesController::class, 'show'])->name('break-types');
        Route::post('break-types', [OnboardingBreakTypesController::class, 'update'])->name('break-types.update');
    });
});

require __DIR__.'/settings.php';
