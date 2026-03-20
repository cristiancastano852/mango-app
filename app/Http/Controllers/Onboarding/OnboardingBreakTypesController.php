<?php

namespace App\Http\Controllers\Onboarding;

use App\Domain\Company\Models\Company;
use App\Domain\TimeTracking\Models\BreakType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingBreakTypesController extends Controller
{
    public function show(Request $request): Response
    {
        $breakTypes = BreakType::where('company_id', $request->user()->company_id)
            ->withoutGlobalScopes()
            ->get(['id', 'name', 'slug', 'icon', 'color', 'is_active', 'is_paid']);

        return Inertia::render('Onboarding/BreakTypes', [
            'breakTypes' => $breakTypes,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'active_ids' => ['present', 'array'],
            'active_ids.*' => ['integer'],
        ]);

        $companyId = $request->user()->company_id;
        $activeIds = $validated['active_ids'];

        BreakType::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get()
            ->each(function (BreakType $breakType) use ($activeIds) {
                $breakType->update(['is_active' => in_array($breakType->id, $activeIds)]);
            });

        Company::findOrFail($companyId)->update(['onboarding_completed' => true]);

        return redirect()->route('dashboard')->with('success', '¡Bienvenido a MangoApp! Tu empresa está lista.');
    }
}
