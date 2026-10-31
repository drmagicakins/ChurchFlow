<?php

namespace App\Http\Controllers;

use App\Domains\Onboarding\Services\SetupWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupWizardController extends Controller
{
    public function __construct(private readonly SetupWizardService $wizard) {}

    public function show(Request $request): View
    {
        $church = $request->user()->church;

        return view('onboarding.setup', [
            'progress' => $this->wizard->progressFor($church),
            'percent' => $this->wizard->percentComplete($church),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $data = $request->validate(['step' => ['required', 'string']]);
        $this->wizard->markStepComplete($request->user()->church, $data['step'], $request->user());

        return back()->with('status', 'Step completed.');
    }

    /**
     * §11/§34: the church profile step's ONE required input — the church's name.
     *
     * Previously `church_profile` could be marked complete without writing
     * anything, so every church carried the placeholder
     * "<owner>'s Church" created at activation (`ActivateChurchFromCheckout`,
     * whose own comment says "the setup wizard collects the real name").
     *
     * Kept as its own endpoint rather than extending complete() so that the
     * other five steps keep their existing one-field contract untouched.
     */
    public function completeProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->hasPermission('settings.manage'), 403,
            'You need permission to manage church settings to change the church name.');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $church = $user->church;
        $name = trim($data['name']);

        $church->update(['name' => $name]);

        // The Headquarters branch is created at activation with the OLD
        // placeholder name (ActivateChurchFromCheckout:76). Keep it in step, but
        // only while it still carries that placeholder — a church that already
        // renamed its HQ deliberately must not have it overwritten.
        $church->organizationalUnits()
            ->where('name', "{$user->name}'s Church")
            ->update(['name' => $name]);

        $this->wizard->markStepComplete($church, 'church_profile', $user);

        return back()->with('status', "Church name saved — welcome, {$name}.");
    }

    public function skip(Request $request): RedirectResponse
    {
        $data = $request->validate(['step' => ['required', 'string']]);
        $this->wizard->skipStep($request->user()->church, $data['step'], $request->user());

        return back()->with('status', 'Skipped — you can finish this later from Setup.');
    }
}
