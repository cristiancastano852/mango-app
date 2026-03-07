<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Company\Models\SurchargeRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSurchargeRuleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SurchargeRuleController extends Controller
{
    public function edit(Request $request): Response
    {
        $rule = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        return Inertia::render('settings/SurchargeRules', [
            'rule' => $rule,
        ]);
    }

    public function update(UpdateSurchargeRuleRequest $request): RedirectResponse
    {
        $rule = SurchargeRule::withoutGlobalScopes()
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $rule->update($request->validated());

        return to_route('surcharge-rules.edit');
    }
}
