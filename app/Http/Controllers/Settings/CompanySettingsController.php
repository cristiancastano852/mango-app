<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Company\Models\Company;
// TODO: Schedules feature temporarily disabled — restore Schedule import when resuming
// use App\Domain\Organization\Models\Schedule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanySettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        $company = $companyId ? Company::find($companyId) : null;

        // TODO: Schedules feature temporarily disabled — restore $schedules query + props when resuming

        $settings = $company?->settings ?? [];

        return Inertia::render('settings/CompanySettings', [
            'workingDays' => $settings['working_days'] ?? [1, 2, 3, 4, 5],
            'hasCompany' => $company !== null,
        ]);
    }

    public function update(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;

        if (! $companyId) {
            return to_route('company-settings.edit');
        }

        $company = Company::findOrFail($companyId);
        $data = $request->validated();

        $workingDays = array_values(array_unique(array_map('intval', $data['working_days'])));
        sort($workingDays);

        $settings = $company->settings ?? [];
        $settings['working_days'] = $workingDays;
        // TODO: Schedules feature temporarily disabled — restore default_schedule_id save when resuming

        $company->settings = $settings;
        $company->save();

        return to_route('company-settings.edit');
    }
}
