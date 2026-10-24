<?php

namespace App\Http\Controllers;

use App\Domains\Events\Jobs\SendEventReminderEmailsJob;
use App\Domains\Events\Jobs\SendEventReminderSmsJob;
use App\Models\Event;
use App\Models\EventRegistrationSubmission;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * Events visible in the app.
     *
     * §14/§37: a parish pastor sees only their own parish's events. Events is
     * a parish-owned table (events.organizational_unit_id), so the TenantScope
     * already narrows the query once IdentifyTenant binds the parish — this
     * explicit `when` is the same rule stated where the list is built, so the
     * page cannot silently start showing every parish if that binding ever
     * changes.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Event::class);

        $user = auth()->user();

        $events = Event::query()
            ->when($user->isParishScoped(), fn ($q) => $q->where('organizational_unit_id', $user->organizational_unit_id))
            ->orderBy('starts_at', 'desc')
            ->paginate(20);

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

        // §14/§37: a parish pastor's event belongs to their parish, whether or
        // not the form offered a branch — a church-wide event they created
        // would be one they could not see again. The TenantScope also fills
        // this in on create, but stating it here keeps the intent readable.
        if ($request->user()->isParishScoped()) {
            $data['organizational_unit_id'] = $request->user()->organizational_unit_id;
        }

        $event = Event::create($data);

        return redirect()->route('events.show', $event);
    }

    public function show(Event $event): View
    {
        $this->authorize('view', $event);

        // The detail page is more than a title: it lists who has registered
        // (and who is waitlisted), and — for someone who can manage events —
        // offers the roster of members who could still be added. Eager-loaded
        // here so the template never N+1s over registrations.
        $event->load(['organizationalUnit', 'organizer']);

        $registrations = $event->registrations()
            ->with('member')
            ->orderBy('registered_at')
            ->get()
            ->sortBy(fn ($r) => $r->member?->full_name ?? '')
            ->values();

        $canManage = auth()->user()->hasPermission('events.manage');

        // §13: everyone who filled in the PUBLIC registration form. These are
        // visitors, not church members, so they are their own list — but the
        // organiser sees them on the same page as the member roster, because
        // "who is coming" has one answer.
        $visitors = $event->visitorRegistrations()
            ->orderByDesc('registered_at')
            ->orderByDesc('id')
            ->get();

        // Members not already on this event's list, so the "add attendee"
        // dropdown never offers someone who is already registered.
        $availableMembers = $canManage
            ? Member::query()
                ->whereNotIn('id', $registrations->pluck('member_id'))
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'email'])
            : collect();

        return view('events.show', compact('event', 'registrations', 'availableMembers', 'canManage', 'visitors'));
    }

    /**
     * §13: mint (or reveal) the event's public registration link.
     *
     * Done on demand rather than at creation so every event already on the
     * calendar gains a form the first time its organiser asks, without a
     * backfill script and without old events silently sharing a broken link.
     */
    public function sharePublicForm(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $slug = PublicEventRegistrationController::ensureSlug($event);
        $event->forceFill(['registration_open' => true])->save();

        return redirect()->route('events.show', $event)
            ->with('status', 'Public registration is open: '.route('events.public.register', $slug))
            ->with('public_link', route('events.public.register', $slug));
    }

    /** §13: take one visitor off the list, keeping the record of their sign-up. */
    public function cancelVisitor(Event $event, EventRegistrationSubmission $visitor): RedirectResponse
    {
        $this->authorize('update', $event);

        // Route-model binding knows nothing about the event–visitor relationship;
        // a crafted id must not cancel another event's (or church's) sign-up.
        abort_unless($visitor->event_id === $event->id, 404);
        abort_unless($visitor->church_id === $event->church_id, 404);

        $visitor->update(['status' => 'cancelled']);

        return back()->with('status', "{$visitor->name} removed from this event's list.");
    }

    /** §16: email every visitor on this event's form a reminder. */
    public function emailReminder(Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $recipients = $event->visitorRegistrations()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('email')
            ->count();

        if ($recipients === 0) {
            return back()->withErrors(['reminder' => 'Nobody who filled in the event form has an email address yet.']);
        }

        SendEventReminderEmailsJob::dispatch($event->id);

        return back()->with('status', "Reminder queued for {$recipients} visitor(s) with an email address.");
    }

    /**
     * §16: bulk SMS to the event's visitor list.
     *
     * Deliberately shares the SMS wallet machinery rather than inventing a
     * second sender: units are reserved when the job runs, failed sends are
     * refunded, and — the rule this screen exists to respect — a parish pastor
     * cannot spend the MAIN church's SMS credits. Only a church-wide manager
     * (owner or registrar) may use the church wallet; a parish-scoped pastor is
     * pointed at their own wallet instead.
     */
    public function smsReminder(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        abort_unless($request->user()->hasPermission('sms.manage'), 403,
            'Sending SMS needs the SMS permission.');

        $recipients = $event->visitorRegistrations()
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();

        if ($recipients === 0) {
            return back()->withErrors(['reminder' => 'Nobody who filled in the event form has a phone number yet.']);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:600'],
        ]);

        SendEventReminderSmsJob::dispatch($event->id, $data['message']);

        return back()->with('status', "SMS reminder queued for {$recipients} visitor(s). Units are reserved from the church SMS wallet as they send.");
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

    /**
     * Removes a member from the event's list. Soft-cancels the registration
     * (status 'cancelled') rather than deleting the row, so the audit trail of
     * "was registered, then withdrawn" survives — the same reasoning the rest
     * of the app uses for financial records. Frees a capacity slot as a
     * side-effect, because confirmedCount() only counts 'registered'.
     */
    public function cancelRegistration(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate(['member_id' => ['required', 'exists:members,id']]);
        $member = Member::findOrFail($data['member_id']);
        abort_unless($member->church_id === $event->church_id, 403);

        $event->registrations()->updateOrCreate(
            ['member_id' => $member->id],
            ['status' => 'cancelled'],
        );

        return back()->with('status', "{$member->full_name} was removed from {$event->title}.");
    }
}
