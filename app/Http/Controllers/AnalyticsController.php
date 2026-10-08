<?php

namespace App\Http\Controllers;

use App\Domains\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Not every §55 event originates server-side (e.g. "feature_discovered" —
 * a new-feature prompt was simply shown/clicked). This endpoint exists for
 * those, but only for an explicit allow-list of event names and a small
 * fixed set of property keys — never an arbitrary event name or payload
 * from the browser, which would turn this into an open logging sink.
 */
class AnalyticsController extends Controller
{
    private const ALLOWED_EVENTS = ['feature_discovered', 'feature_used'];

    public function track(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(self::ALLOWED_EVENTS)],
            'feature' => ['nullable', 'string', 'max:100'],
        ]);

        $analytics->track($data['event'], $request->user(), $request->user()->church, [
            'feature' => $data['feature'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }
}
