<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Domains\PlatformAdmin\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function edit(): View
    {
        return view('platform-admin.settings.edit', [
            'smsPricePerUnit' => $this->settings->smsPricePerUnit(),
            'defaultTaxRate' => $this->settings->defaultTaxRate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_price_per_unit' => ['required', 'numeric', 'min:0'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->settings->set('sms_price_per_unit', $data['sms_price_per_unit']);
        $this->settings->set('default_tax_rate', $data['default_tax_rate']);

        return back()->with('status', 'Platform settings updated.');
    }
}
