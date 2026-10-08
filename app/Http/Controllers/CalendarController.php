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
        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : now()->endOfMonth();

        $items = $calendar->itemsBetween($from, $to);

        return view('calendar.index', compact('items', 'from', 'to'));
    }
}
