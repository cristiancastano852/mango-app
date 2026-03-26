<?php

namespace App\Http\Controllers\Onboarding;

use App\Domain\Company\Models\Company;
use App\Domain\Organization\Models\Schedule;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingScheduleController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Onboarding/Schedule');
    }

    public function update(Request $request): RedirectResponse
    {
        if (! $request->boolean('skip')) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
                'days_of_week' => ['required', 'array', 'min:1'],
                'days_of_week.*' => ['integer', 'between:0,6'],
            ]);

            $schedule = Schedule::create([
                'company_id' => $request->user()->company_id,
                'name' => $validated['name'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'days_of_week' => $validated['days_of_week'],
            ]);

            $company = Company::findOrFail($request->user()->company_id);
            $settings = $company->settings ?? [];
            $settings['default_schedule_id'] = $schedule->id;
            $company->update(['settings' => $settings]);
        }

        return redirect()->route('onboarding.break-types');
    }
}
