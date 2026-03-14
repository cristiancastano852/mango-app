<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Schedule;
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

        $schedules = $companyId
            ? Schedule::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
            : collect();

        $settings = $company?->settings ?? [];

        return Inertia::render('settings/CompanySettings', [
            'workingDays' => $settings['working_days'] ?? [1, 2, 3, 4, 5],
            'defaultScheduleId' => $settings['default_schedule_id'] ?? null,
            'schedules' => $schedules,
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
        $settings['default_schedule_id'] = isset($data['default_schedule_id']) && $data['default_schedule_id'] !== ''
            ? (int) $data['default_schedule_id']
            : null;

        $company->settings = $settings;
        $company->save();

        return to_route('company-settings.edit');
    }
}
