<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class PlanController extends Controller
{
    /** Public: the landing page pricing section and the post-registration plan picker share this. */
    public function index(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('plans.index', compact('plans'));
    }
}
