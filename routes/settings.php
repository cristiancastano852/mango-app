<?php

use App\Http\Controllers\Settings\BreakTypeController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\HolidayController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SurchargeRuleController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware(['auth', 'verified', 'role:admin|super-admin'])->group(function () {
    Route::get('settings/company-profile', [CompanyProfileController::class, 'edit'])
        ->name('company-profile.edit');
    Route::put('settings/company-profile', [CompanyProfileController::class, 'update'])
        ->name('company-profile.update');

    Route::get('settings/company-settings', [CompanySettingsController::class, 'edit'])
        ->name('company-settings.edit');
    Route::put('settings/company-settings', [CompanySettingsController::class, 'update'])
        ->name('company-settings.update');

    Route::get('settings/surcharge-rules', [SurchargeRuleController::class, 'edit'])
        ->name('surcharge-rules.edit');
    Route::put('settings/surcharge-rules', [SurchargeRuleController::class, 'update'])
        ->name('surcharge-rules.update');

    Route::resource('settings/holidays', HolidayController::class)
        ->names('holidays')
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('settings/break-types', BreakTypeController::class)
        ->names('break-types')
        ->only(['index', 'store', 'update']);
    Route::patch('settings/break-types/{break_type}/toggle', [BreakTypeController::class, 'toggleActive'])
        ->name('break-types.toggle');
});
