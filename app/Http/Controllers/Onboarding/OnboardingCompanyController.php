<?php

namespace App\Http\Controllers\Onboarding;

use App\Domain\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateCompanyProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingCompanyController extends Controller
{
    public function show(Request $request): Response
    {
        $company = Company::findOrFail($request->user()->company_id);

        return Inertia::render('Onboarding/Company', [
            'company' => [
                'name' => $company->name,
                'country' => $company->country,
                'timezone' => $company->timezone,
                'logo' => $company->logo ? Storage::disk('public')->url($company->logo) : null,
            ],
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request): RedirectResponse
    {
        $company = Company::findOrFail($request->user()->company_id);
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

        return redirect()->route('onboarding.schedule');
    }
}
