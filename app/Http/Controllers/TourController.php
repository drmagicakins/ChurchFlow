<?php

namespace App\Http\Controllers;

use App\Domains\Onboarding\Services\TourEngineService;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints: the tour overlay itself is a frontend concern (§29's
 * "animated transitions and visual callouts") that reads this API to
 * decide what to render — this controller only ever returns state, never
 * HTML for the tour itself.
 */
class TourController extends Controller
{
    public function __construct(private readonly TourEngineService $tours) {}

    /** What the current page should offer — e.g. "Show me" for a newly released feature (§30). */
    public function shouldShow(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json([
            'should_show' => $this->tours->shouldShow($request->user(), $tour),
            'tour' => [
                'slug' => $tour->slug,
                'name' => $tour->name,
                'version' => $tour->version,
                'steps' => $tour->steps->map(fn ($s) => [
                    'title' => $s->title,
                    'description' => $s->description,
                    'target_selector' => $s->target_selector,
                    'position' => $s->position,
                    'action_url' => $s->action_url,
                    'image_url' => $s->image_url,
                ]),
            ],
        ]);
    }

    public function start(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->start($request->user(), $tour));
    }

    public function next(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->next($request->user(), $tour));
    }

    public function previous(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->previous($request->user(), $tour));
    }

    public function finish(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->finish($request->user(), $tour));
    }

    public function skip(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->skip($request->user(), $tour));
    }

    public function dismissPermanently(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->dismissPermanently($request->user(), $tour));
    }

    public function restart(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::where('slug', $slug)->firstOrFail();

        return response()->json($this->tours->restart($request->user(), $tour));
    }
}
