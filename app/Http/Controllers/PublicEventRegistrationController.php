<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The PUBLIC event page and registration form (§13).
 *
 * Everything here is visitor-facing and works without an account: a church
 * shares one link, and a guest fills in their details. That shapes three
 * decisions:
 *
 *  1. NO tenant middleware runs on these routes — a visitor has no session and
 *     no church. The event is found by its slug across every church, and every
 *     query below is filtered by the CHURCH OF THAT EVENT, never by "the
 *     current tenant". A slug is unique per church, so a lookup can never pick
 *     up another church's event by accident.
 *  2. The page exposes only what is safe to publish: the title, when/where, the
 *     description and the organiser's church name. No member data, no roster,
 *     no counts beyond places remaining.
 *  3. Capacity is enforced HERE, inside a transaction, against BOTH the in-app
 *     roster and the public list — the same "two people cannot take the last
 *     seat" rule the in-app registration already follows.
 */
class PublicEventRegistrationController extends Controller
{
    public function show(string $slug): View
    {
        $event = $this->findOpenEvent($slug);

        $registered = $event->visitorRegistrations()
            ->where('status', 'registered')
            ->sum(DB::raw('1 + guests'));

        $seatsLeft = $event->capacity === null
            ? null
            : max($event->capacity - ($event->confirmedCount() + (int) $registered), 0);

        return view('events.public-register', [
            'event' => $event,
            'church' => $event->church,
            'seatsLeft' => $seatsLeft,
            'isFull' => $seatsLeft === 0,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $event = $this->findOpenEvent($slug);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'organization' => ['nullable', 'string', 'max:255'],
            'guests' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'email.required_without' => 'Give us an email address or a phone number so we can reach you.',
            'phone.required_without' => 'Give us an email address or a phone number so we can reach you.',
        ]);

        $party = 1 + (int) ($data['guests'] ?? 0);

        // Derived per request, not stored: a "registered twice" double-click is
        // the single most common public-form problem, and the same email or
        // phone for the same event is the same person.
        $email = isset($data['email']) ? mb_strtolower(trim($data['email'])) : null;
        $phone = isset($data['phone']) ? trim($data['phone']) : null;

        $alreadyThere = $event->visitorRegistrations()
            ->where('status', '!=', 'cancelled')
            ->when($email, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))
            ->when(! $email && $phone, fn ($q) => $q->where('phone', $phone))
            ->exists();

        if ($alreadyThere) {
            return redirect()
                ->route('events.public.register', $slug)
                ->with('status', 'You are already on the list for this event. See you there!');
        }

        $status = DB::transaction(function () use ($event, $party) {
            // Lock the event row so two visitors cannot both take the last seat.
            $locked = Event::withoutGlobalScopes()->whereKey($event->id)->lockForUpdate()->first();

            $inApp = $locked->confirmedCount();
            $visitors = $locked->visitorRegistrations()->where('status', 'registered')->sum(DB::raw('1 + guests'));

            $fits = $locked->capacity === null
                || ($inApp + (int) $visitors + $party) <= $locked->capacity;

            return $fits ? 'registered' : 'waitlisted';
        });

        $event->visitorRegistrations()->create([
            // No tenant is bound on a public request, so BelongsToTenant cannot
            // fill this in — and it must not: the church is the EVENT's, never
            // anything derived from the visitor.
            'church_id' => $event->church_id,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $phone,
            'organization' => $data['organization'] ?? null,
            'guests' => (int) ($data['guests'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'status' => $status,
            'source' => 'public_form',
            'ip_address' => $request->ip(),
            'registered_at' => now(),
        ]);

        return redirect()
            ->route('events.public.register', $slug)
            ->with('status', $status === 'registered'
                ? 'You are registered. A confirmation has been recorded for the organiser.'
                : 'The event is full, so you have been added to the waitlist. The organiser will be in touch.');
    }

    /**
     * Find the event behind a public slug.
     *
     * `withoutGlobalScopes()` is deliberate and safe: there is no tenant bound
     * on a public request, so the global scope would otherwise return nothing
     * at all. The slug plus an explicit church_id on every follow-up query is
     * what keeps the lookup isolated; a cancelled or soft-deleted event is not
     * findable, so a shared link to an event that was called off does not
     * quietly keep taking registrations.
     */
    private function findOpenEvent(string $slug): Event
    {
        $event = Event::withoutGlobalScopes()
            ->with(['church', 'organizationalUnit'])
            ->where('public_slug', $slug)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->firstOrFail();

        abort_unless($event->registration_open, 404);

        return $event;
    }

    /**
     * Mint a slug for an event that does not have one yet. Called from the
     * organiser's screen, so the link exists the moment they ask for it rather
     * than at creation time (which would have needed a migration for every
     * event already on the calendar).
     */
    public static function ensureSlug(Event $event): string
    {
        if ($event->public_slug) {
            return $event->public_slug;
        }

        $base = Str::slug($event->title) ?: 'event';
        $slug = Str::limit($base, 40, '');

        // Scoped to this church: the unique index is (church_id, public_slug),
        // so the same title in two churches must not collide.
        $candidate = $slug;
        $i = 2;
        while (Event::withoutGlobalScopes()
            ->where('church_id', $event->church_id)
            ->where('public_slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$i++;
        }

        $event->forceFill(['public_slug' => $candidate])->save();

        return $candidate;
    }
}
