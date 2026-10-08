<?php

namespace App\Http\Controllers;

use App\Models\SubventionPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubventionPeriodController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', \App\Models\SubventionSubmission::class);
        $periods = SubventionPeriod::query()->latest('period_start')->get();

        return view('subvention.periods.index', compact('periods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Models\SubventionRuleSet::class); // same "manage" permission gate

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
        ]);

        $period = SubventionPeriod::create($data);

        return redirect()->route('subvention.periods.index')->with('status', "Period \"{$period->name}\" created.");
    }
}
