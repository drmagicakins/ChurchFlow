<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

/**
 * The public "/" route — unauthenticated, no tenant context. Pricing is
 * the one genuinely dynamic part: it reads straight from the `plans`
 * table (same data PlanController/the checkout flow use), so a price or
 * limit changed by a platform admin shows up here with no template edit.
 * The dashboard-preview panel stays static sample data on purpose — it's
 * illustrative marketing content, not a window into anyone's real church,
 * and wiring it to live data would mean exposing something from the
 * database to a logged-out visitor.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        return view('landing.index', [
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
