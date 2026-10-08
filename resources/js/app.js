import './bootstrap';

// Alpine powers the marketing pages' interactive bits (mobile menu, Resources
// dropdown, pricing toggle, FAQ accordions). It is registered globally rather
// than imported per-component so a ported marketing component can use x-data
// with no build-time wiring of its own.
//
// The landing-page package replaced this file wholesale with a bare Alpine
// bootstrap, which would have dropped the `./bootstrap` import above (axios +
// CSRF header) that the rest of the application relies on. Merged instead.
import Alpine from 'alpinejs';

window.Alpine = Alpine;

/*
 | ---------------------------------------------------------------------------
 | Chart.js — bundled, not CDN-loaded
 | ---------------------------------------------------------------------------
 | The dashboard's Financial Overview chart used to load Chart.js from
 | cdn.jsdelivr.net with a bare <script src>. Two problems with that:
 |
 |  1. It makes a core dashboard panel depend on a third party being reachable
 |     at page-view time. On an offline/air-gapped network, or behind a
 |     firewall that blocks jsdelivr, the request fails and the chart renders
 |     as an empty box — an object the user is told exists but cannot see.
 |  2. `new Chart(...)` was called unguarded, so a failed load threw
 |     "Chart is not defined" into the console instead of failing quietly.
 |
 | Importing it here puts it in the same hashed, versioned bundle as the rest
 | of the app: no external request, no CDN drift, and it works offline. It is
 | exposed on window so the existing inline chart bootstrap in
 | dashboard.blade.php keeps working unchanged.
 */
import Chart from 'chart.js/auto';

window.Chart = Chart;

/*
 | ---------------------------------------------------------------------------
 | Scroll reveal
 | ---------------------------------------------------------------------------
 | Deliberately hand-rolled instead of pulling in a library. It is ~40 lines, has
 | no dependencies to audit, and the whole behaviour is "add a class once".
 |
 | Two guards matter more than the animation itself:
 |
 |  1. `js-reveal` is set on <html> HERE, not in the stylesheet. The CSS only
 |     hides [data-reveal] elements while that class is present, so if this file
 |     fails to load the content is fully visible rather than permanently
 |     transparent. A reveal script whose failure mode is a blank page is a
 |     liability on a marketing site.
 |
 |  2. If IntersectionObserver is unavailable, or the user prefers reduced motion,
 |     everything is revealed immediately and synchronously. No observer, no
 |     animation, no risk of content stuck at opacity 0.
 */
const prefersReducedMotion = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

function initReveal() {
    const nodes = document.querySelectorAll('[data-reveal]');
    if (nodes.length === 0) return;

    const revealAll = () => nodes.forEach((el) => el.classList.add('is-revealed'));

    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
        revealAll();
        return;
    }

    document.documentElement.classList.add('js-reveal');

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                // Unobserve: these are one-shot entrances. Leaving the observer
                // attached means the animation replays every time the element
                // scrolls back into view, which reads as a glitch, and it keeps
                // a reference to every node on the page for no benefit.
                obs.unobserve(entry.target);
            });
        },
        {
            // Fire slightly before the element reaches the viewport edge so the
            // motion resolves as it settles into view rather than after it has
            // already been sitting there.
            rootMargin: '0px 0px -8% 0px',
            threshold: 0.05,
        }
    );

    nodes.forEach((el) => observer.observe(el));

    /*
     | Safety net.
     |
     | IntersectionObserver fires on scroll, which covers the normal case but not
     | every case where content can end up on screen without a scroll event:
     |
     |   - a deep link (/#pricing) puts a visitor mid-page, and everything ABOVE
     |     their landing point has already been scrolled past and never intersects
     |     from the top — it stays at opacity 0 for the whole visit;
     |   - browser scroll restoration after a refresh, same problem;
     |   - printing or "Save as PDF" renders the full document height at once and
     |     the observer never runs, so the PDF is blank between sections;
     |   - anchor jumps and in-page find.
     |
     | Rather than trying to enumerate those, this reveals anything that is not
     | still ahead of the viewport whenever the scroll position settles. It runs
     | once after load and again 1.5s later, which also covers a lazily-sized
     | document (web font swap, image load) shifting the page after the observer
     | was constructed.
     |
     | This is verification, not decoration: the content was never allowed to
     | depend on an animation to become visible in the first place.
     */
    const sweep = () => {
        const limit = window.scrollY + window.innerHeight;
        nodes.forEach((el) => {
            if (el.classList.contains('is-revealed')) return;
            // getBoundingClientRect().top + scrollY is the element's absolute
            // top, which is what we compare to the viewport bottom.
            const top = el.getBoundingClientRect().top + window.scrollY;
            if (top < limit + 200) el.classList.add('is-revealed');
        });
    };

    window.addEventListener('load', sweep, { once: true });
    window.setTimeout(sweep, 1500);

    // If the document is restored at a deep scroll offset, run immediately.
    if (window.scrollY > 0) sweep();

    // Belt and braces for print: nothing should be invisible on paper.
    window.addEventListener('beforeprint', revealAll);
}

/*
 | ---------------------------------------------------------------------------
 | Count-up
 | ---------------------------------------------------------------------------
 | Animates [data-count-to] elements when they scroll into view.
 |
 | The end value is already in the markup, and this only replaces it after
 | deciding to animate — so with JS off, or reduced motion on, the real figure is
 | what was server-rendered. The animation is a presentation detail layered on top
 | of correct content, never the mechanism that supplies the content.
 */
function initCountUp() {
    const nodes = document.querySelectorAll('[data-count-to]');
    if (nodes.length === 0) return;

    if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') return;

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                obs.unobserve(entry.target);
                animateCount(entry.target);
            });
        },
        { threshold: 0.4 }
    );

    nodes.forEach((el) => observer.observe(el));
}

function animateCount(el) {
    const target = Number(el.dataset.countTo);
    if (!Number.isFinite(target) || target <= 0) return;

    const prefix = el.dataset.countPrefix ?? '';
    const suffix = el.dataset.countSuffix ?? '';
    const duration = Number(el.dataset.countDuration ?? 1100);
    const start = performance.now();

    const format = (value) => `${prefix}${Math.round(value).toLocaleString('en-NG')}${suffix}`;

    function frame(now) {
        const progress = Math.min((now - start) / duration, 1);
        // easeOutCubic: fast out of the gate, gently settling. A linear count
        // reads as a loading spinner; this reads as a number arriving.
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = format(target * eased);

        if (progress < 1) {
            requestAnimationFrame(frame);
        } else {
            el.textContent = format(target);
        }
    }

    requestAnimationFrame(frame);
}

/*
 | ---------------------------------------------------------------------------
 | Data grid row selection
 | ---------------------------------------------------------------------------
 | Shared by every module that adopts the `cfg-` data grid (Members first;
 | Finance/Events reuse this same component rather than each rolling their
 | own checkbox state). Selection lives in memory only — reloading the page
 | (a new search/sort/filter) naturally clears it, which is correct: a
 | selection made against one filtered view shouldn't silently carry over to
 | a different one.
 */
Alpine.data('cfGridSelection', (pageIds) => ({
    selected: [],
    pageIds,
    get count() {
        return this.selected.length;
    },
    get allOnPageSelected() {
        return (
            this.pageIds.length > 0 &&
            this.pageIds.every((id) => this.selected.includes(id))
        );
    },
    toggle(id) {
        const i = this.selected.indexOf(id);
        if (i === -1) this.selected.push(id);
        else this.selected.splice(i, 1);
    },
    toggleAllOnPage() {
        this.selected = this.allOnPageSelected
            ? this.selected.filter((id) => !this.pageIds.includes(id))
            : [...new Set([...this.selected, ...this.pageIds])];
    },
    clear() {
        this.selected = [];
    },
}));

function boot() {
    initReveal();
    initCountUp();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

Alpine.start();
