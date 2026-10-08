<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Services\PlanCatalog;
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
        // Phase 12: the four tiers — Starter, Growth, Denomination and
        // Enterprise — sourced from PlanCatalog, which lists every marketing
        // tier (including Enterprise, which has no purchasable row) with its
        // resolved price. Previously this filtered on `plans.is_active`, so
        // Enterprise was invisible on the landing page even though the pricing
        // copy advertised it: two pages disagreeing about whether a tier
        // exists, never mind what it costs.
        return view('landing.index', [
            'plans' => app(PlanCatalog::class)->forDisplay(),
        ]);
    }
}
