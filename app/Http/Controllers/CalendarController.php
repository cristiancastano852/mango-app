<?php

namespace App\Http\Controllers;

use App\Domain\Employee\Models\Employee;
use App\Domain\TimeTracking\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::parse($month.'-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $entries = TimeEntry::with('employee.user')
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('date')
            ->map(fn ($dayEntries) => $dayEntries->map(fn ($entry) => [
                'id' => $entry->id,
                'employee_id' => $entry->employee_id,
                'employee_name' => $entry->employee->user->name,
                'clock_in' => $entry->clock_in?->format('H:i'),
                'clock_out' => $entry->clock_out?->format('H:i'),
                'net_hours' => (float) $entry->net_hours,
                'status' => $entry->status,
            ])->values());

        return Inertia::render('Calendar/Index', [
            'month' => $month,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'entriesByDate' => $entries,
            'employees' => Employee::with('user')->get()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->user->name,
            ]),
            'filters' => $request->only(['employee_id']),
        ]);
    }
}
