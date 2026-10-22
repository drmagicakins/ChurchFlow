{{--
 | Submit-button loading state, applied globally.
 |
 | The problem this solves is not cosmetic. Double-submitting a form is one of
 | the few UI bugs that causes real data damage here: two identical tithes
 | recorded, two subvention submissions for the same branch and period, two
 | SMS campaigns reserved. The financial and subvention paths are idempotent
 | where they can be, but a slow submit that appears to have done nothing
 | invites a second click, and no amount of server-side guarding makes that
 | click a good idea.
 |
 | So this is a delegated listener, not a per-form opt-in: any form carrying
 | `data-cf-loading` (and every form that does NOT carry `data-cf-no-loading`)
 | gets it automatically. Opt-out rather than opt-in on purpose — a new form
 | added in a later phase should be protected by default, and it is far easier
 | to miss adding an attribute than to miss having it.
 |
 | On submit it: marks the form busy, disables the submit control(s) so a
 | second press is impossible, swaps in a spinner while KEEPING the button's
 | width, and sets aria-busy so assistive tech is told the same thing the
 | pixels are. If the browser restores this page from the back/forward cache
 | the state has to be undone, or the button stays dead forever — hence the
 | pageshow handler.
--}}

<div id="cf-loader-overlay" class="cf-loaderoverlay" hidden aria-hidden="true">
    <div class="cf-loaderoverlay__panel" role="status" aria-live="polite">
        <span class="cf-spinner cf-spinner--lg" aria-hidden="true"></span>
        <p class="cf-loaderoverlay__text" data-cf-loader-text>Working…</p>
    </div>
</div>

<style>
    .cf-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: cf-spin 0.6s linear infinite;
        vertical-align: -0.15em;
    }

    .cf-spinner--lg {
        width: 2rem;
        height: 2rem;
        border-width: 3px;
    }

    @keyframes cf-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Respect a stated preference for less motion: the spinner still appears
       (it communicates "working"), it just stops rotating. Making it vanish
       entirely would remove the only feedback that something is happening. */
    @media (prefers-reduced-motion: reduce) {
        .cf-spinner {
            animation-duration: 2.4s;
        }
    }

    .cf-btn.is-busy {
        pointer-events: none;
        opacity: 0.75;
    }

    .cf-btn__label {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .cf-loaderoverlay {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(15, 23, 42, 0.32);
        backdrop-filter: blur(1.5px);
    }

    .cf-loaderoverlay__panel {
        background: var(--surface, #fff);
        border-radius: 0.85rem;
        padding: 1.4rem 1.8rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.7rem;
        min-width: 13rem;
        box-shadow: 0 24px 48px -20px rgba(15, 23, 42, 0.55);
    }

    .cf-loaderoverlay__text {
        margin: 0;
        font-size: 0.86rem;
        font-weight: 600;
        color: var(--ink, #0f172a);
    }

    /* A form mid-submit must not look editable, or the user will keep typing
       into fields whose values are already on the wire. */
    form.is-submitting input:not([type=hidden]),
    form.is-submitting select,
    form.is-submitting textarea {
        pointer-events: none;
        opacity: 0.85;
    }
</style>

<script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
    (function() {
        var overlay = document.getElementById('cf-loader-overlay');

        function showOverlay(form) {
            if (!overlay) return;
            var explicit = form.getAttribute('data-cf-loading-text');
            var text = overlay.querySelector('[data-cf-loader-text]');
            if (text) text.textContent = explicit || 'Working…';
            overlay.hidden = false;
            overlay.setAttribute('aria-hidden', 'false');
        }

        // Undoes busy() AND hides the overlay. Used when a submit turns out not
        // to be a navigation after all (the confirm dialog was declined), on
        // bfcache restore, and by window.cfLoader.reset().
        function reset(form) {
            if (form) {
                form.classList.remove('is-submitting');
                form.removeAttribute('aria-busy');
                form.querySelectorAll('[data-cf-busy]').forEach(function(btn) {
                    btn.classList.remove('is-busy');
                    btn.disabled = false;
                    btn.removeAttribute('aria-disabled');
                    btn.removeAttribute('data-cf-busy');
                    var sp = btn.querySelector('.cf-spinner');
                    if (sp) sp.remove();
                    var busyText = btn.querySelector('.cf-btn__busytext');
                    if (busyText) busyText.remove();
                    var lbl = btn.querySelector('.cf-btn__label');
                    if (lbl) lbl.hidden = false;
                });
            }

            if (overlay) {
                overlay.hidden = true;
                overlay.setAttribute('aria-hidden', 'true');
            }
        }

        // Reset every form the page marked busy. A submit can be cancelled
        // AFTER this listener has already run (see the submit handler below),
        // so the recovery path must not depend on knowing which form it was.
        function resetAll() {
            document.querySelectorAll('form.is-submitting').forEach(reset);
            // Belt and braces: if a form somehow lost its class, the overlay
            // must still not be left covering the page.
            if (overlay) {
                overlay.hidden = true;
                overlay.setAttribute('aria-hidden', 'true');
            }
        }

        window.cfLoaderResetAll = resetAll;

        function busy(form) {
            form.classList.add('is-submitting');
            form.setAttribute('aria-busy', 'true');

            var buttons = form.querySelectorAll('button[type=submit], input[type=submit]');

            buttons.forEach(function(btn) {
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
                btn.dataset.cfBusy = '1';

                // Only decorate a real <button>; an <input type=submit> has no
                // child nodes to attach a spinner to, so it is simply disabled.
                if (btn.tagName === 'BUTTON' && !btn.querySelector('.cf-spinner')) {
                    btn.classList.add('is-busy');
                    var label = btn.querySelector('.cf-btn__label');
                    if (label) label.hidden = true;

                    var sp = document.createElement('span');
                    sp.className = 'cf-spinner';
                    sp.setAttribute('aria-hidden', 'true');
                    var word = document.createElement('span');
                    word.className = 'cf-btn__busytext';
                    word.textContent = btn.getAttribute('data-cf-loading-label') || 'Working…';

                    btn.appendChild(sp);
                    btn.appendChild(word);
                }
            });
        }

        // Delegated, so it covers forms rendered later (a panel opened by a
        // toggle, a table row's inline form) without re-binding anything.
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.hasAttribute('data-cf-no-loading')) return;

            // A form the browser will handle natively (method="dialog", or
            // target=_blank) is not a navigation, so a full-page overlay would
            // be wrong.
            if (form.getAttribute('target')) return;

            // A CONFIRMABLE form is never submitted on this pass. The confirm
            // dialog prevents the submit and re-submits only if the user
            // accepts, so showing "Working…" here would mean showing it for a
            // form that has not been approved — and nothing would ever clear it
            // when the user pressed Cancel.
            //
            // `e.defaultPrevented` alone is NOT enough to detect this: this
            // listener is registered before partials.confirm's (loader is
            // included first in the layout), so at this moment the dialog has
            // not yet called preventDefault() and the flag is still false. That
            // gap is exactly what left the overlay stuck on screen after Cancel.
            if (form.hasAttribute('data-confirm')) return;

            // Never interfere with a submit the page itself cancelled — e.g.
            // Alpine's @submit.prevent on the global search box.
            if (e.defaultPrevented) return;

            busy(form);
            showOverlay(form);
        });

        // The last line of defence: if a submit is cancelled after this
        // listener has already started the spinner (any interceptor that runs
        // later than this file), put everything back. Scheduled as a
        // microtask so every other submit listener — regardless of include
        // order — has had the chance to call preventDefault() first.
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            queueMicrotask(function() {
                if (e.defaultPrevented) reset(form);
            });
        });

        // Browser back/forward can restore a page with the button still
        // disabled from a previous submit. Without this, that button is dead.
        window.addEventListener('pageshow', function(e) {
            if (!e.persisted) return;
            resetAll();
        });

        // Exposed so a page can drive the same state from its own async work
        // (e.g. the health page's "refresh checks" without a navigation).
        window.cfLoader = {
            busy: busy,
            reset: reset,
            show: showOverlay
        };
    })();
</script>
