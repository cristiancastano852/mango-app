<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        $request->session()->put('tour_dismissed', true);

        return redirect()->route('dashboard');
    }
}
