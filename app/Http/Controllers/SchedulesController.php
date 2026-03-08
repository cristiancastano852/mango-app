<?php

namespace App\Http\Controllers;

use App\Domain\Organization\Models\Schedule;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SchedulesController extends Controller
{
    public function index(): Response
    {
        $schedules = Schedule::withCount('employees')->get();

        return Inertia::render('Schedules/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Schedules/Create');
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        Schedule::create($request->validated());

        return redirect()->route('schedules.index')
            ->with('success', __('messages.schedule_created'));
    }

    public function edit(Schedule $schedule): Response
    {
        $schedule->load('employees.user');

        return Inertia::render('Schedules/Edit', [
            'schedule' => $schedule,
        ]);
    }

    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $schedule->update($request->validated());

        return redirect()->route('schedules.index')
            ->with('success', __('messages.schedule_updated'));
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('schedules.index')
            ->with('success', __('messages.schedule_deleted'));
    }
}
