<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\PrayerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrayerRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PrayerRequest::class);

        $requests = PrayerRequest::query()
            ->visibleTo($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(25);

        return view('pastoral.prayer-requests.index', ['requests' => $requests]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', PrayerRequest::class);

        $data = $request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'submitted_by_name' => ['nullable', 'string', 'max:255'],
            'request' => ['required', 'string', 'max:5000'],
            'is_confidential' => ['boolean'],
        ]);

        if (empty($data['member_id'])) {
            $data['submitted_by_name'] ??= 'Anonymous';
        }

        $prayerRequest = PrayerRequest::create($data);

        return redirect()->route('pastoral.prayer-requests.index')->with('status', 'Prayer request logged.');
    }

    public function update(Request $request, PrayerRequest $prayerRequest): RedirectResponse
    {
        $this->authorize('update', $prayerRequest);

        $data = $request->validate([
            'status' => ['required', 'in:open,praying,answered,closed'],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        $prayerRequest->update($data);

        return back()->with('status', 'Updated.');
    }
}
