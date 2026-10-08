<?php

namespace App\Http\Controllers;

use App\Domains\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /** Live suggestions for the topbar overlay: a handful of hits per module. */
    public function suggest(Request $request, GlobalSearchService $search): JsonResponse
    {
        // A missing/short query is a normal state while typing, not an error.
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        return response()->json([
            'query' => $term,
            'groups' => $search->search($request->user(), $term, 5),
        ]);
    }

    /** "View all results": the same search, with a longer list per module. */
    public function index(Request $request, GlobalSearchService $search): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        return view('search.index', [
            'term' => $term,
            'tooShort' => $term !== '' && mb_strlen($term) < GlobalSearchService::MIN_LENGTH,
            'groups' => $search->search($request->user(), $term, 25),
        ]);
    }
}
