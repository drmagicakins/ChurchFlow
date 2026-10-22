{{--
 | §24: the task reminder popup.
 |
 | Polls /tasks/reminders/due on load and every couple of minutes. When a task's
 | remind_at has passed and it has not been acknowledged, this opens a modal and
 | plays a short chime.
 |
 | The chime is synthesised with the Web Audio API rather than shipped as an
 | .mp3: no binary asset to host, no autoplay-blocked <audio> element to fight,
 | and it stays silent until the user has interacted with the page at all (a
 | browser rule we cannot opt out of — so a reminder that arrives before any
 | click simply shows the visual popup and skips the sound).
 |
 | Dismissing a reminder POSTs to /tasks/{task}/reminder/dismiss, which stamps
 | reminder_sent_at so it never reappears.
--}}

<div x-data="cfTaskReminders()" x-cloak>
    <div class="cf-confirm" x-show="visible" x-transition.opacity role="presentation">
        <div class="cf-confirm__scrim" @click="dismissAll()" aria-hidden="true"></div>

        <div class="cf-confirm__panel" role="alertdialog" aria-modal="true" aria-labelledby="cf-reminder-title">
            <div class="cf-confirm__icon cf-confirm__icon--primary" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round" width="26" height="26">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                </svg>
            </div>

            <h2 class="cf-confirm__title" id="cf-reminder-title" x-text="current ? current.title : 'Task reminder'">
            </h2>
            <p class="cf-confirm__message">
                <template x-if="current && current.description">
                    <span x-text="current.description"></span>
                </template>
                <template x-if="current">
                    <span>
                        <br>
                        <span class="cf-tiny cf-muted">
                            Reminder set for
                            <span x-text="formatDate(current.remind_at)"></span>
                        </span>
                    </span>
                </template>
                <template x-if="remaining > 0">
                    <span class="cf-tiny cf-muted"
                        x-text="' · ' + (remaining + 1) + ' more reminder' + (remaining === 0 ? '' : 's')"></span>
                </template>
            </p>

            <div class="cf-confirm__actions">
                <button type="button" class="cf-btn cf-btn--secondary" @click="dismissAll()">Dismiss all</button>
                <button type="button" class="cf-btn cf-btn--primary" @click="dismissCurrent()">Got it</button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('cfTaskReminders', () => ({
            tasks: [],
            current: null,
            visible: false,
            timer: null,
            shown: [],
            _audioCtx: null,

            get remaining() {
                return this.tasks.length > 0 ? this.tasks.length - 1 : 0;
            },

            init() {
                // Do not poll on the login/checkout pages — those render outside
                // the app shell, and the endpoint needs a tenant context anyway.
                if (!document.querySelector('.cf-shell')) return;

                // Reminders already put on screen in this browser. The server
                // also filters these out (once_a_day), but this stops a single
                // session from re-opening the SAME reminder on every page while
                // the user clicks around — which is exactly the "alert on every
                // page load" experience this closes.
                this.shown = JSON.parse(sessionStorage.getItem('cf.reminders.shown') || '[]');

                this.fetchDue();
                this.timer = setInterval(() => this.fetchDue(), 120000); // every 2 minutes
            },

            _remember(id) {
                if (!this.shown.includes(id)) {
                    this.shown.push(id);
                    try {
                        sessionStorage.setItem('cf.reminders.shown', JSON.stringify(this.shown));
                    } catch (e) { /* private mode — fall back to the server filter */ }
                }
            },

            async fetchDue() {
                try {
                    // `once_a_day=1`: a reminder is an announcement, not a nag.
                    // Without it every page load re-asked for every reminder
                    // that had already been shown and never explicitly clicked
                    // "Got it" — a dismissed/closing tab, a reload, the scrim —
                    // so the popup re-appeared on EVERY page. The server still
                    // answers for a new reminder immediately; one already put on
                    // screen is left out until the next day.
                    const res = await fetch('{{ route('tasks.reminders.due') }}?once_a_day=1', {
                        headers: {
                            Accept: 'application/json'
                        },
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    const tasks = (data.tasks || []).filter(
                        (t) => !this.tasks.some((shown) => shown.id === t.id) && !this.shown.includes(t.id)
                    );
                    if (!tasks.length) return;

                    this.tasks = this.tasks.concat(tasks);
                    if (!this.visible) {
                        this.current = this.tasks[0];
                        this.visible = true;
                        this._remember(this.current.id);
                        this.chime();
                    }
                } catch (e) {
                    /* offline or unauthenticated — try again on the next tick */
                }
            },

            formatDate(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                return d.toLocaleString(undefined, {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            },

            async dismissCurrent() {
                const task = this.current;
                this._advance();
                if (task) await this.markSeen(task.id);
            },

            async dismissAll() {
                const all = this.tasks.slice();
                this.tasks = [];
                this.current = null;
                this.visible = false;
                for (const t of all) {
                    await this.markSeen(t.id);
                }
            },

            async markSeen(id) {
                try {
                    await fetch('/tasks/' + id + '/reminder/dismiss', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            Accept: 'application/json',
                        },
                    });
                } catch (e) {
                    /* the popup still closes; it may re-appear next load */
                }
            },

            _advance() {
                this.tasks = this.tasks.filter((t) => t !== this.current);
                this.current = this.tasks[0] || null;
                this.visible = !!this.current;
                if (this.current) this._remember(this.current.id);
            },

            /** A short two-note chime. Silently skipped if audio is blocked. */
            chime() {
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return;
                    this._audioCtx = this._audioCtx || new Ctx();
                    const ctx = this._audioCtx;
                    if (ctx.state === 'suspended') {
                        ctx.resume().catch(() => {});
                    }

                    const notes = [880, 1174.66]; // A5 then D6
                    notes.forEach((freq, i) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.value = freq;
                        const start = ctx.currentTime + i * 0.18;
                        gain.gain.setValueAtTime(0.0001, start);
                        gain.gain.exponentialRampToValueAtTime(0.28, start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.16);
                        osc.connect(gain).connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + 0.18);
                    });
                } catch (e) {
                    /* no sound available — the popup is still shown */
                }
            },
        }));
    });
</script>
