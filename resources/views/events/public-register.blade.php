<x-layouts.auth :title="$event->title . ' · Registration'" :heading="$event->title" :subheading="$church?->name ? 'Registration · ' . $church->name : 'Event registration'">

    <div class="cf-card" style="display:grid;gap:1rem">
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:.5rem 1rem;margin:0">
            <dt class="cf-label" style="margin:0">When</dt>
            <dd class="cf-small" style="margin:0">
                {{ $event->starts_at?->format('l, j M Y · g:i A') }}
                @if ($event->ends_at)
                    <br><span class="cf-muted">until {{ $event->ends_at->format('l, j M Y · g:i A') }}</span>
                @endif
            </dd>

            <dt class="cf-label" style="margin:0">Where</dt>
            <dd class="cf-small" style="margin:0">{{ $event->venue ?: 'To be announced' }}</dd>

            @if ($seatsLeft !== null)
                <dt class="cf-label" style="margin:0">Places</dt>
                <dd class="cf-small" style="margin:0">
                    {{ number_format($seatsLeft) }} of {{ number_format($event->capacity) }} left
                </dd>
            @endif
        </dl>

        @if ($event->description)
            <p class="cf-small" style="white-space:pre-line;margin:0">{{ $event->description }}</p>
        @endif
    </div>

    @if ($isFull)
        <div class="cf-alert cf-alert--warn" role="status" style="margin-top:1.2rem">
            <span class="cf-alert__icon"><x-ui.icon name="info" class="h-4 w-4" /></span>
            <div class="cf-alert__body">
                <p class="cf-alert__title">This event is full</p>
                <p class="cf-small" style="margin:.3rem 0 0">You can still register and we will add you to the
                    waitlist — the organiser will contact you if a place frees up.</p>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('events.public.store', $event->public_slug) }}" class="cf-card"
        style="display:grid;gap:1rem;margin-top:1.2rem">
        @csrf

        <h2 style="font-size:1.05rem;font-weight:700;margin:0">Register for this event</h2>
        <p class="cf-small cf-muted" style="margin:0">No account needed — just tell us how to reach you.</p>

        <div class="cf-field">
            <label class="cf-label" for="reg-name">Your full name <span class="cf-label__req"
                    aria-hidden="true">*</span></label>
            <input type="text" id="reg-name" name="name" class="cf-input" value="{{ old('name') }}"
                autocomplete="name" required aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
            @error('name')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="cf-field">
            <label class="cf-label" for="reg-email">Email address</label>
            <input type="email" id="reg-email" name="email" class="cf-input" value="{{ old('email') }}"
                autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            @error('email')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @else
                <p class="cf-hint">We use this only to send you event reminders.</p>
            @enderror
        </div>

        <div class="cf-field">
            <label class="cf-label" for="reg-phone">Phone number</label>
            <input type="tel" id="reg-phone" name="phone" class="cf-input" value="{{ old('phone') }}"
                autocomplete="tel" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
            @error('phone')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @else
                <p class="cf-hint">Email or phone — at least one, so the organiser can reach you.</p>
            @enderror
        </div>

        <div class="cf-field">
            <label class="cf-label" for="reg-org">Church or organisation</label>
            <input type="text" id="reg-org" name="organization" class="cf-input" value="{{ old('organization') }}"
                autocomplete="organization">
            @error('organization')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="cf-field">
            <label class="cf-label" for="reg-guests">Guests coming with you</label>
            <input type="number" id="reg-guests" name="guests" class="cf-input" min="0" max="50"
                value="{{ old('guests', 0) }}" aria-invalid="{{ $errors->has('guests') ? 'true' : 'false' }}">
            @error('guests')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="cf-field">
            <label class="cf-label" for="reg-notes">Anything we should know?</label>
            <textarea id="reg-notes" name="notes" class="cf-textarea" rows="3">{{ old('notes') }}</textarea>
            @error('notes')
                <p class="cf-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="cf-btn cf-btn--primary">
            {{ $isFull ? 'Join the waitlist' : 'Register' }}
        </button>

        <p class="cf-tiny cf-muted" style="margin:0">
            Your details are shared only with {{ $church?->name ?? 'the church' }} for the purposes of this event.
        </p>
    </form>

</x-layouts.auth>
