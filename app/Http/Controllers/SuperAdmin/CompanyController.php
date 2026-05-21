<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Domain\Company\Actions\CreateCompanyAdminUser;
use App\Domain\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreAdminUserRequest;
use App\Http\Requests\SuperAdmin\UpdateCompanyRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()
            ->select('id', 'name', 'slug', 'subscription_plan', 'created_at')
            ->latest()
            ->get();

        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
        ]);
    }

    public function edit(Company $company): Response
    {
        $admins = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->role('admin')
            ->select('id', 'name', 'email', 'is_active')
            ->get();

        return Inertia::render('SuperAdmin/Companies/Edit', [
            'company' => $company->only('id', 'name', 'slug', 'logo', 'timezone', 'country', 'subscription_plan', 'trial_ends_at', 'onboarding_completed'),
            'admins' => $admins,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return redirect()->route('super-admin.companies.edit', $company)
            ->with('success', __('messages.company_updated'));
    }

    public function storeAdminUser(StoreAdminUserRequest $request, Company $company, CreateCompanyAdminUser $action): RedirectResponse
    {
        [, $plainPassword] = $action->execute($company, $request->validated('name'), $request->validated('email'));

        return redirect()->route('super-admin.companies.edit', $company)
            ->with('success', __('messages.admin_user_created'))
            ->with('created_password', $plainPassword);
    }
}
