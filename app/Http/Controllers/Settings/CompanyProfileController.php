<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanyProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        $company = $companyId
            ? Company::find($companyId)
            : null;

        return Inertia::render('settings/CompanyProfile', [
            'company' => $company ? [
                'name' => $company->name,
                'logo' => $company->logo ? Storage::disk('public')->url($company->logo) : null,
                'country' => $company->country,
                'timezone' => $company->timezone,
            ] : null,
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request): RedirectResponse
    {
        $companyId = $request->user()->company_id;

        if (! $companyId) {
            return to_route('company-profile.edit');
        }

        $company = Company::findOrFail($companyId);
        $data = $request->validated();

        if ($request->boolean('remove_logo') && $company->logo) {
            Storage::disk('public')->delete($company->logo);
            $company->logo = null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }

            $company->logo = $request->file('logo')->store('logos', 'public');
        }

        $company->name = $data['name'];
        $company->country = $data['country'];
        $company->timezone = $data['timezone'];
        $company->save();

        return to_route('company-profile.edit');
    }
}
