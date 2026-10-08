<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Domains\FeatureFlags\Services\FeatureFlagService;
use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureFlagController extends Controller
{
    public function __construct(private readonly FeatureFlagService $flags) {}

    public function index(): View
    {
        return view('platform-admin.feature-flags.index', [
            'flags' => FeatureFlag::with('churchOverrides.church')->get(),
            'churches' => Church::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->flags->setGlobal($data['key'], false, $data['label'], $data['description'] ?? null);

        return back()->with('status', 'Feature flag created (disabled by default).');
    }

    public function toggleGlobal(Request $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $this->flags->setGlobal($featureFlag->key, !$featureFlag->is_globally_enabled, $featureFlag->label, $featureFlag->description);

        return back();
    }

    public function setChurchOverride(Request $request, FeatureFlag $featureFlag): RedirectResponse
    {
        $data = $request->validate([
            'church_id' => ['required', 'exists:churches,id'],
            'enabled' => ['required', 'boolean'],
        ]);

        $this->flags->setForChurch($featureFlag->key, Church::findOrFail($data['church_id']), $data['enabled']);

        return back()->with('status', 'Override saved.');
    }
}
