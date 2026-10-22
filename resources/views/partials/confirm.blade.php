{{--
 | A single, custom confirmation dialog for the whole app.
 |
 | WHY THIS EXISTS
 | ---------------
 | Destructive actions were guarded with the browser's native
 | `confirm()`. That is not just ugly — it is unstyled, un-brandable,
 | blocks the whole event loop, and on some mobile browsers the "Cancel"
 | and "OK" buttons are ordered differently from the app's own buttons,
 | so people delete the wrong thing. It also cannot be themed, localised,
 | or audited.
 |
 | HOW IT WORKS
 | ------------
 | Any form (or button) that carries `data-confirm="…"` is intercepted
 | here. The dialog opens with the given message; "OK" submits the form
 | exactly as it would have, "Cancel" returns false and nothing happens —
 | which is the behaviour the brief asks for, applied uniformly.
 |
 | A plain `<form onsubmit="return confirm(...)">` still works, so no
 | older page breaks while it is migrated, but nothing new should use it.
 |
 | The dialog is driven by Alpine so it participates in the same reactive
 | tree as the rest of the app and can be opened programmatically via
 | `window.cfConfirm(message)` returning a Promise<boolean>.
--}}

<div x-data="cfConfirmDialog()" x-cloak @cf-confirm-open.window="open($event.detail)" x-on:keydown.escape.window="cancel()">
    <div class="cf-confirm" x-show="visible" x-transition.opacity role="presentation">
        <div class="cf-confirm__scrim" @click="cancel()" aria-hidden="true"></div>

        <div class="cf-confirm__panel" role="alertdialog" aria-modal="true" aria-labelledby="cf-confirm-title"
            aria-describedby="cf-confirm-message">
            <div class="cf-confirm__icon" :class="'cf-confirm__icon--' + (tone || 'danger')" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round" width="26" height="26">
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                    <path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z" />
                </svg>
            </div>

            <h2 class="cf-confirm__title" id="cf-confirm-title" x-text="title || 'Are you sure?'"></h2>
            <p class="cf-confirm__message" id="cf-confirm-message" x-text="message"></p>

            <div class="cf-confirm__actions">
                <button type="button" class="cf-btn cf-btn--secondary" @click="cancel()" x-ref="cancelBtn"
                    x-text="cancelLabel || 'Cancel'"></button>
                <button type="button" class="cf-btn"
                    :class="tone === 'danger' ? 'cf-btn--danger' : 'cf-btn--primary'" @click="accept()"
                    x-text="confirmLabel || 'OK'"></button>
            </div>
        </div>
    </div>
</div>

<style>
    .cf-confirm {
        position: fixed;
        inset: 0;
        z-index: 90;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .cf-confirm__scrim {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(2px);
    }

    .cf-confirm__panel {
        position: relative;
        z-index: 1;
        width: min(26rem, 100%);
        background: var(--surface, #fff);
        border: 1px solid var(--line, #e5e7eb);
        border-radius: 0.9rem;
        padding: 1.5rem;
        box-shadow: 0 30px 60px -20px rgba(15, 23, 42, 0.6);
        text-align: center;
    }

    .cf-confirm__icon {
        width: 3rem;
        height: 3rem;
        margin: 0 auto 0.9rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cf-confirm__icon--danger {
        background: rgba(185, 28, 28, 0.12);
        color: var(--danger, #b91c1c);
    }

    .cf-confirm__icon--primary {
        background: rgba(22, 119, 255, 0.12);
        color: var(--cf-brand, #1677ff);
    }

    .cf-confirm__title {
        margin: 0 0 0.4rem;
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--ink, #0f172a);
    }

    .cf-confirm__message {
        margin: 0 0 1.2rem;
        font-size: 0.9rem;
        line-height: 1.5;
        color: var(--muted, #64748b);
    }

    .cf-confirm__actions {
        display: flex;
        gap: 0.6rem;
        justify-content: flex-end;
    }

    .cf-confirm__actions .cf-btn {
        flex: 1 1 auto;
    }

    @media (min-width: 26rem) {
        .cf-confirm__actions .cf-btn {
            flex: 0 0 auto;
        }
    }

    .cf-btn--danger {
        background: var(--danger, #b91c1c);
        border-color: var(--danger, #b91c1c);
        color: #fff;
    }

    .cf-btn--danger:hover {
        filter: brightness(0.94);
    }
</style>

<script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
    document.addEventListener('alpine:init', () => {
        Alpine.data('cfConfirmDialog', () => ({
            visible: false,
            title: 'Are you sure?',
            message: '',
            tone: 'danger',
            confirmLabel: 'OK',
            cancelLabel: 'Cancel',
            _onConfirm: null,
            _onCancel: null,
            _lastFocus: null,

            init() {
                // Intercept every data-confirm submission app-wide. Delegated
                // so tables/panels rendered later are covered without rebinding.
                document.addEventListener('submit', (e) => {
                    const form = e.target;
                    if (!(form instanceof HTMLFormElement)) return;
                    const msg = form.getAttribute('data-confirm');
                    if (!msg) return;

                    // Already approved by this dialog on a previous pass.
                    if (form.dataset.cfConfirmed === '1') {
                        delete form.dataset.cfConfirmed;
                        return;
                    }

                    e.preventDefault();
                    e.stopImmediatePropagation();

                    this.open({
                        message: msg,
                        title: form.getAttribute('data-confirm-title') ||
                            'Are you sure?',
                        tone: form.getAttribute('data-confirm-tone') || 'danger',
                        confirmLabel: form.getAttribute('data-confirm-ok') || 'OK',
                        cancelLabel: form.getAttribute('data-confirm-cancel') ||
                            'Cancel',
                        onConfirm: () => {
                            form.dataset.cfConfirmed = '1';

                            // Show the submit state now that the user has
                            // actually approved it. The loader intentionally
                            // ignores data-confirm forms (it cannot know yet
                            // whether they will be confirmed), so this is the
                            // moment it must take over. form.submit() alone
                            // fires no submit event, so nothing else would.
                            if (typeof window.cfLoader?.busy === 'function') {
                                window.cfLoader.busy(form);
                                window.cfLoader.show(form);
                            }

                            form.submit();
                        },
                    });
                });
            },

            open(detail) {
                this.title = detail.title || 'Are you sure?';
                this.message = detail.message || '';
                this.tone = detail.tone || 'danger';
                this.confirmLabel = detail.confirmLabel || 'OK';
                this.cancelLabel = detail.cancelLabel || 'Cancel';
                this._onConfirm = detail.onConfirm || null;
                this._onCancel = detail.onCancel || null;
                this._lastFocus = document.activeElement;
                this.visible = true;

                requestAnimationFrame(() => {
                    if (this.$refs.cancelBtn) this.$refs.cancelBtn.focus();
                });
            },

            accept() {
                const fn = this._onConfirm;
                this.close();
                if (typeof fn === 'function') fn();
                return true;
            },

            cancel() {
                const fn = this._onCancel;
                this.close();
                if (typeof fn === 'function') fn();

                // Belt and braces for the loader: if the form that opened this
                // dialog had already been marked busy (or a submit interceptor
                // ran in the wrong order and showed the overlay), the spinner
                // must not survive a decline. Doing it here means Cancel is
                // correct even if the loader's own reordering defence is ever
                // changed or removed.
                if (typeof window.cfLoaderResetAll === 'function') {
                    window.cfLoaderResetAll();
                }

                return false;
            },

            close() {
                this.visible = false;
                this._onConfirm = null;
                this._onCancel = null;
                if (this._lastFocus && this._lastFocus.focus) {
                    this._lastFocus.focus();
                }
            },
        }));
    });

    // Programmatic API: const ok = await window.cfConfirm('Remove this?');
    window.cfConfirm = function(message, options) {
        options = options || {};
        return new Promise((resolve) => {
            window.dispatchEvent(new CustomEvent('cf-confirm-open', {
                detail: {
                    message: message,
                    title: options.title,
                    tone: options.tone,
                    confirmLabel: options.confirmLabel,
                    cancelLabel: options.cancelLabel,
                    onConfirm: () => resolve(true),
                    onCancel: () => resolve(false),
                },
            }));
        });
    };
</script>
