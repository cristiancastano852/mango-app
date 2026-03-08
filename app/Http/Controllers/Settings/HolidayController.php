<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Company\Models\Holiday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreHolidayRequest;
use App\Http\Requests\Settings\UpdateHolidayRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(Request $request): Response
    {
        $holidays = Holiday::withoutGlobalScopes()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('date')
            ->get();

        return Inertia::render('settings/Holidays', [
            'holidays' => $holidays,
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::create(array_merge($request->validated(), [
            'company_id' => $request->user()->company_id,
            'country' => 'CO',
        ]));

        return to_route('holidays.index');
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validated());

        return to_route('holidays.index');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return to_route('holidays.index');
    }
}
