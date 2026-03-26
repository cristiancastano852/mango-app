<?php

namespace App\Http\Controllers;

use App\Domain\Company\Actions\RegisterCompany;
use App\Http\Requests\RegisterCompanyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyRegistrationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('auth/RegisterCompany');
    }

    public function store(RegisterCompanyRequest $request, RegisterCompany $action): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()->route('register.company.create');
        }

        $action->execute($request->validated());

        return redirect()->route('onboarding.company');
    }
}
