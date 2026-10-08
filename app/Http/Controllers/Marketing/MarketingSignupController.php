<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 |--------------------------------------------------------------------------
 | Marketing signup entry point
 |--------------------------------------------------------------------------
 | Where the landing page hands off into the paid onboarding journey.
 |
 | This is its own file, not a second class inside HomeController.php, because
 | PSR-4 maps one class to one file: a second class in that file autoloads under
 | the file's name and the container cannot resolve it. Verified the hard way —
 | /get-started returned a 500 with "Target class
 | [App\Http\Controllers\Marketing\MarketingSignupController] does not exist".
 |
 | The landing page's "Get Started" buttons point here rather than straight at
 | /register, so that when the billing module lands there is exactly one method to
 | change. Today it validates the optional ?plan= against config and forwards to
 | /register with the choice preserved.
 |
 | It is deliberately a GET and it creates nothing — no user, no church tenant, no
 | subscription. A tenant must not exist before payment is verified (brief,
 | section 12), and a landing-page route is the easiest place to accidentally do
 | it by treating a signup click as a provisioning trigger.
 */

class MarketingSignupController extends Controller
{
    /**
     * Landing-page entry into onboarding. /get-started (optionally ?plan=growth)
     */
    public function start(Request $request): RedirectResponse
    {
        $plan = $request->query('plan');

        // Only forward a plan key that actually exists in config. Without this,
        // a hand-typed ?plan=<anything> would be carried into the next step and
        // could be echoed back or stored, so the value is whitelisted here rather
        // than trusted downstream.
        $valid = collect(config('marketing.plans'))->pluck('key')->all();

        if (is_string($plan) && in_array($plan, $valid, true)) {
            return redirect()->route('register', ['plan' => $plan]);
        }

        return redirect()->route('register');
    }
}