<?php

namespace App\Http\Controllers;

use App\Models\SubventionRuleSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubventionRuleSetController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SubventionRuleSet::class);
        $ruleSets = SubventionRuleSet::with('rules')->get();

        return view('subvention.rule-sets.index', compact('ruleSets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SubventionRuleSet::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.name' => ['required', 'string', 'max:255'],
            'rules.*.base_field' => ['nullable', 'string', 'max:255'],
            'rules.*.type' => ['required', 'in:percentage,fixed,capped_percentage'],
            'rules.*.rate' => ['nullable', 'numeric', 'min:0'],
            'rules.*.fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.cap_amount' => ['nullable', 'numeric', 'min:0'],
            'rules.*.classification' => ['required', 'in:retention,deduction'],
        ]);

        $ruleSet = SubventionRuleSet::create(['name' => $data['name']]);

        foreach ($data['rules'] as $i => $rule) {
            $ruleSet->rules()->create([...$rule, 'sort_order' => $i]);
        }

        return redirect()->route('subvention.rule-sets.show', $ruleSet);
    }

    public function show(SubventionRuleSet $ruleSet): View
    {
        $this->authorize('view', $ruleSet);

        return view('subvention.rule-sets.show', ['ruleSet' => $ruleSet->load('rules')]);
    }
}
