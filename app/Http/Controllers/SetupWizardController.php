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

    public function skip(Request $request): RedirectResponse
    {
        $data = $request->validate(['step' => ['required', 'string']]);
        $this->wizard->skipStep($request->user()->church, $data['step'], $request->user());

        return back()->with('status', 'Skipped — you can finish this later from Setup.');
    }
}
