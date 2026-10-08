<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Event::class);
        $events = Event::query()->orderBy('starts_at', 'desc')->paginate(20);

        return view('events.index', compact('events'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Event::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'organizational_unit_id' => ['nullable', 'exists:organizational_units,id'],
        ]);

        $data['organizer_id'] = $request->user()->id;
        $event = Event::create($data);

        return redirect()->route('events.show', $event);
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        return view('events.show', compact('event'));
    }

    /**
     * §13: registration with capacity enforcement. This is intentionally
     * NOT "insert and hope" — it checks capacity inside the same
     * transaction the row is created in, so two people registering for the
     * last slot at the same moment can't both get in.
     */
    public function register(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('view', $event);

        $data = $request->validate(['member_id' => ['required', 'exists:members,id']]);
        $member = Member::findOrFail($data['member_id']);
        abort_unless($member->church_id === $event->church_id, 403);

        $status = \Illuminate\Support\Facades\DB::transaction(function () use ($event, $member) {
            $event = Event::whereKey($event->id)->lockForUpdate()->first();
            $status = $event->hasCapacityFor(1) ? 'registered' : 'waitlisted';

            $event->registrations()->updateOrCreate(
                ['member_id' => $member->id],
                ['status' => $status, 'registered_at' => now()],
            );

            return $status;
        });

        return back()->with('status', $status === 'registered'
            ? 'Registered for the event.'
            : 'Event is full — added to the waitlist.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);
        $event->update(['status' => 'cancelled']);
        $event->delete();

        return redirect()->route('events.index');
    }
}
