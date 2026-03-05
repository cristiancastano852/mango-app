<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Admin-only routes
    Route::middleware('role:admin|super-admin')->group(function () {
        Route::resource('employees', \App\Http\Controllers\EmployeeController::class);
    });

    // Time Clock (all authenticated employees)
    Route::get('time-clock', [\App\Http\Controllers\TimeClockController::class, 'index'])->name('time-clock.index');
    Route::post('time-clock/clock-in', [\App\Http\Controllers\TimeClockController::class, 'clockIn'])->name('time-clock.clock-in');
    Route::post('time-clock/clock-out', [\App\Http\Controllers\TimeClockController::class, 'clockOut'])->name('time-clock.clock-out');
    Route::post('time-clock/break/start', [\App\Http\Controllers\TimeClockController::class, 'startBreak'])->name('time-clock.break.start');
    Route::post('time-clock/break/end', [\App\Http\Controllers\TimeClockController::class, 'endBreak'])->name('time-clock.break.end');
});

require __DIR__.'/settings.php';
