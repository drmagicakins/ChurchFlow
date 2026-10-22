<?php

namespace App\Http\Controllers;

use App\Domains\Calendar\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request, CalendarService $calendar): View
    {
        // §14: the page is a month grid, so the canonical parameter is a single
        // `month` (YYYY-MM). `from`/`to` are still honoured for link/bookmark
        // compatibility, but the grid is always a whole month.
        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString().'-01')
            : ($request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now());

        $grid = $calendar->monthGrid($month->copy());

        $from = $grid['weeks'][0][0]->copy()->startOfDay();
        $to = $grid['weeks'][count($grid['weeks']) - 1][6]->copy()->endOfDay();

        $items = $calendar->itemsBetween($from, $to);

        // Index by date so the grid can pull a day's items in one lookup
        // instead of filtering the whole collection 42 times.
        $byDate = $items->groupBy('date');

        return view('calendar.index', [
            'items' => $items,
            'byDate' => $byDate,
            'weeks' => $grid['weeks'],
            'label' => $grid['label'],
            'month' => $month,
            'prevMonth' => $grid['prev'],
            'nextMonth' => $grid['next'],
            'from' => $from,
            'to' => $to,
        ]);
    }
}
