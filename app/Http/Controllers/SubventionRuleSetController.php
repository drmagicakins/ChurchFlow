<?php

namespace App\Http\Controllers;

use App\Models\SubventionRuleSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $data = $this->validated($request);

        // Wrapped in a transaction: a rule set with a name but no rules (or a
        // half-inserted set) is worse than no rule set at all, because the
        // calculation engine would silently compute nothing against it.
        $ruleSet = DB::transaction(function () use ($data) {
            $ruleSet = SubventionRuleSet::create(['name' => $data['name']]);

            foreach (array_values($data['rules']) as $i => $rule) {
                $ruleSet->rules()->create([...$rule, 'sort_order' => $i]);
            }

            return $ruleSet;
        });

        return redirect()->route('subvention.rule-sets.show', $ruleSet)
            ->with('status', "Rule set \"{$ruleSet->name}\" created.");
    }

    /**
     * Editing a rule set replaces its rules wholesale.
     *
     * Subtraction is deliberate: a rule removed from the form must actually
     * stop applying. The alternative — diffing and updating in place — keeps
     * rows a user believes they deleted. Past calculations are unaffected
     * either way, because SubventionCalculation stores its own breakdown
     * array at calculation time (see the migration's own note on this).
     */
    public function update(Request $request, SubventionRuleSet $ruleSet): RedirectResponse
    {
        $this->authorize('update', $ruleSet);

        $data = $this->validated($request);

        DB::transaction(function () use ($ruleSet, $data) {
            $ruleSet->update(['name' => $data['name']]);
            $ruleSet->rules()->delete();

            foreach (array_values($data['rules']) as $i => $rule) {
                $ruleSet->rules()->create([...$rule, 'sort_order' => $i]);
            }
        });

        return redirect()->route('subvention.rule-sets.show', $ruleSet)
            ->with('status', "Rule set \"{$ruleSet->name}\" updated.");
    }

    /**
     * The validation shared by store() and update() — one shape, so a rule
     * that is valid when created is valid when edited, and vice versa.
     *
     * @return array{name: string, rules: array<int, array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
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
    }

    public function show(SubventionRuleSet $ruleSet): View
    {
        $this->authorize('view', $ruleSet);

        return view('subvention.rule-sets.show', ['ruleSet' => $ruleSet->load('rules')]);
    }
}
