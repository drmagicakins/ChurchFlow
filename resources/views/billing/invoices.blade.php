<x-layout title="Invoices · ChurchFlow">
    @php
        // Money formatting is shared with billing/show so a figure can never be
        // rendered two different ways on two billing screens. A proration credit
        // is a negative `amount` (§13) and prints as such, deliberately.
        $currencySymbol = fn(?string $code) => \App\Models\Plan::currencySymbol($code);
        $money = fn($value, ?string $currency = null) => $currencySymbol($currency) . number_format((float) $value, 2);

        $typeLabel = fn(?string $type) => match ($type) {
            'subscription' => 'Subscription',
            'sms_credits' => 'SMS credits',
            default => $type ? ucfirst(str_replace('_', ' ', $type)) : '—',
        };

        $statusTone = fn(?string $status) => match ($status) {
            'paid' => 'cf-badge--ok',
            'pending', 'processing', 'unpaid' => 'cf-badge--warn',
            'failed', 'refunded', 'void' => 'cf-badge--danger',
            default => '',
        };

        // Filters are read from the same query string the controller paginates
        // with, so the links, the summary figures and the table always describe
        // the same set of invoices.
        $typeFilter = $type ?? null;
        $statusFilter = $status ?? null;
        $visible = $invoices->getCollection();
        $pageTotal = $visible->sum(fn($invoice) => (float) $invoice->total());
        $hasFilters = filled($typeFilter) || filled($statusFilter);
    @endphp

    @php
        // The church name and the viewer's email head the invoice "Billed to"
        // block. Both come from the signed-in account; nothing is invented when
        // one is missing.
        $churchName = auth()->user()?->church?->name ?? 'Your church';
        $accountEmail = auth()->user()?->email;

        // Every row carries its own printable invoice. Built once per row here
        // rather than fetched on click, so View/Print/Download work with no
        // extra request and no second, divergent source of figures.
        $documents = $invoices->getCollection()->mapWithKeys(
            fn($invoice) => [
                $invoice->id => $invoiceDocument($invoice, $summary['currency'] ?? null, $churchName, $accountEmail),
            ],
        );
    @endphp

    {{-- ------------------------------------------------------------------ styles
         Scoped to this page (cfi- prefix) so nothing here can leak into another
         screen. Print rules hide the app shell and print ONLY the invoice
         document currently on screen, so "Print" is a real invoice, not a
         screenshot of a dashboard. --}}
    <style>
        .cfi-actions {
            display: inline-flex;
            gap: .5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .cfi-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            padding: .85rem 1.1rem;
            border-bottom: 1px solid var(--line);
        }

        .cfi-filters {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .cfi-filters .cf-input {
            padding-top: .4rem;
            padding-bottom: .4rem;
            font-size: .82rem;
            width: auto;
            min-width: 9.5rem;
        }

        .cfi-chips {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
        }

        .cfi-chip {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .3rem .6rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--surface);
            font-size: .78rem;
            color: var(--muted);
            text-decoration: none;
        }

        .cfi-chip:hover {
            border-color: #cbd5e1;
            color: var(--ink);
        }

        .cfi-chip.is-active {
            background: var(--brand-tint);
            border-color: var(--color-brand-200, #c7d2fe);
            color: var(--brand-dark);
            font-weight: 600;
        }

        .cfi-row {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: nowrap;
        }

        /* The two action buttons must sit side by side in one cell; the
           narrower column is what wrapped them onto two lines. */
        .cfi-row .cf-btn {
            white-space: nowrap;
        }

        .cfi-desc {
            display: block;
            font-weight: 550;
            color: var(--ink);
        }

        .cfi-ref {
            display: block;
            font-size: .74rem;
            color: var(--muted);
            margin-top: .15rem;
        }

        .cfi-dialog {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: none;
            background: rgba(15, 23, 42, .72);
            padding: clamp(.5rem, 3vw, 2.5rem);
            padding-top: max(4.5rem, clamp(.5rem, 3vw, 2.5rem));
            overflow: auto;
        }

        .cfi-dialog.is-open {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        /* On a short viewport the document scrolls from the top instead of
           being clipped above the fold. */
        .cfi-dialog.is-open>* {
            flex: none;
        }

        .cfi-dialog__bar {
            width: min(100%, 52rem);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: .5rem;
            margin: 0 auto .75rem;
            flex-wrap: wrap;
        }

        /* The overlay covers the sticky topbar, so these controls need their
           own solid surfaces rather than the page's ghost styling. */
        .cfi-dialog__bar .cf-btn--ghost {
            background: #fff;
            border: 1px solid var(--line);
            color: var(--ink);
        }

        /* ---------------------------------------------------------- the document */
        .cfi-doc {
            max-width: 52rem;
            margin: 0 auto;
            background: #fff;
            color: var(--ink);
            border-radius: .75rem;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .25);
            padding: clamp(1.4rem, 4vw, 2.6rem);
            font-size: .9rem;
            line-height: 1.6;
        }

        .cfi-doc__top {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: wrap;
            padding-bottom: 1.3rem;
            border-bottom: 1px solid var(--line);
        }

        .cfi-brand {
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .cfi-brand__mark {
            width: 2.6rem;
            height: 2.6rem;
            border-radius: .7rem;
            flex: none;
            background: var(--brand);
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 800;
            letter-spacing: -.02em;
        }

        .cfi-brand__name {
            font-weight: 750;
            font-size: 1.05rem;
            letter-spacing: -.02em;
        }

        .cfi-brand__tag {
            font-size: .74rem;
            color: var(--muted);
        }

        .cfi-doc__title {
            text-align: right;
        }

        .cfi-doc__title h2 {
            margin: 0;
            font-size: 1.45rem;
            letter-spacing: -.03em;
            text-transform: uppercase;
            color: var(--brand-dark);
        }

        .cfi-doc__title .cfi-meta {
            font-size: .78rem;
            color: var(--muted);
            margin-top: .35rem;
        }

        .cfi-doc__title .cfi-meta strong {
            color: var(--ink);
        }

        .cfi-parties {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
            gap: 1.2rem;
            margin: 1.4rem 0;
        }

        .cfi-parties h4 {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .09em;
            color: var(--muted);
            margin: 0 0 .35rem;
            font-weight: 700;
        }

        .cfi-parties p {
            margin: 0;
        }

        .cfi-parties .cfi-strong {
            font-weight: 650;
        }

        .cfi-lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: .5rem;
        }

        .cfi-lines th {
            text-align: left;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            font-weight: 700;
            padding: .55rem .75rem;
            background: var(--canvas);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
        }

        .cfi-lines td {
            padding: .8rem .75rem;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }

        .cfi-lines .cfi-num {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .cfi-totals {
            margin-left: auto;
            width: min(100%, 20rem);
            margin-top: 1rem;
            /* Keeps the right-aligned figures off the page/sheet edge — the
               "Tax" dash was being clipped by the print margin. */
            padding-right: .25rem;
            box-sizing: border-box;
        }

        .cfi-totals dl {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: .45rem 1rem;
            margin: 0;
            font-size: .88rem;
        }

        .cfi-totals dt {
            color: var(--muted);
        }

        .cfi-totals dd {
            margin: 0;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .cfi-totals .cfi-due {
            border-top: 1px solid var(--ink);
            padding-top: .6rem;
            margin-top: .35rem;
            font-weight: 750;
            font-size: 1rem;
        }

        .cfi-totals .cfi-due dt {
            color: var(--ink);
        }

        .cfi-doc__foot {
            margin-top: 1.6rem;
            padding-top: 1rem;
            border-top: 1px dashed var(--line);
            font-size: .76rem;
            color: var(--muted);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .cfi-stamp {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-weight: 700;
            color: var(--ok-ink, #065f46);
            background: var(--ok-bg, #ecfdf5);
            border: 1px solid var(--ok-line, #a7f3d0);
            border-radius: 999px;
            padding: .25rem .7rem;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        @media print {
            @page {
                size: A4;
                margin: 14mm;
            }

            html,
            body {
                background: #fff !important;
            }

            body * {
                visibility: hidden !important;
            }

            /* `visibility` alone leaves the app shell occupying its full height,
               so the invoice was pushed far down page 1 (it rendered on the
               first screenful of white). Taking the shell out of flow makes the
               dialog — the only visible thing left — start at the page top. */
            .cf-shell {
                display: block !important;
                height: auto !important;
                min-height: 0 !important;
            }

            .cf-main,
            .cf-content {
                display: block !important;
                height: auto !important;
                min-height: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: none !important;
            }

            .cf-sidebar,
            .cf-topbar,
            .cf-navscrim,
            .cf-page-head,
            .cf-grid,
            .cf-card,
            .cf-empty,
            .cf-table-wrap {
                display: none !important;
            }

            .cfi-dialog.is-open,
            .cfi-dialog.is-open * {
                visibility: visible !important;
            }

            .cfi-dialog {
                position: static !important;
                inset: auto !important;
                display: block !important;
                background: none !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            /* The page's own column width must not carry into the printed
               sheet, or the invoice renders centred in a 52rem block instead
               of filling the A4 page. */
            .cfi-doc {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                box-sizing: border-box;
            }

            .cfi-dialog__bar {
                display: none !important;
            }

            /* The @page margin (14mm) is normally the page's breathing room.
               Where it is not applied — a browser printing just the dialog
               subtree, or the PDF "Save as PDF" capture — a small horizontal
               inset keeps content off the page edge instead of clipping the
               invoice title. */
            .cfi-doc {
                max-width: none;
                margin: 0;
                box-shadow: none !important;
                border-radius: 0;
                padding: 0 2mm;
                font-size: 11pt;
            }

            .cfi-lines th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .cfi-stamp {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .cfi-lines,
            .cfi-totals {
                break-inside: avoid;
            }
        }
    </style>

    <div class="cf-page-head">
        <div>
            <h1>Invoices</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Every charge on your account — subscription periods, plan changes and SMS credit purchases.
                Open any invoice to download or print it.
            </p>
        </div>
        <div class="cfi-actions">
            <a href="{{ route('billing.show') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Back to billing</a>
        </div>
    </div>

    {{-- ---------------------------------------------------------- statement summary
         Three figures that answer the questions people actually open this page for:
         what have I been billed overall, where does it sit now, and what does the
         filtered view on screen add up to. --}}
    <div class="cf-grid cf-grid--3" style="margin-bottom:1.2rem">
        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">Total billed</span>
                <span
                    class="cf-stat__value">{{ $money($summary['paid_total'] ?? 0, $summary['currency'] ?? null) }}</span>
                <span class="cf-stat__hint">{{ $summary['paid_count'] ?? 0 }} paid
                    {{ \Illuminate\Support\Str::plural('invoice', $summary['paid_count'] ?? 0) }}</span>
            </div>
        </div>
        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">On this page</span>
                <span class="cf-stat__value">{{ $money($pageTotal, $summary['currency'] ?? null) }}</span>
                <span class="cf-stat__hint">
                    {{ $visible->count() }} of {{ $invoices->total() }}
                    {{ \Illuminate\Support\Str::plural('invoice', $invoices->total()) }}
                </span>
            </div>
        </div>
        <div class="cf-card">
            <div class="cf-stat">
                <span class="cf-stat__label">This period</span>
                <span
                    class="cf-stat__value">{{ $money($summary['month_total'] ?? 0, $summary['currency'] ?? null) }}</span>
                <span class="cf-stat__hint">{{ now()->format('F Y') }}</span>
            </div>
        </div>
    </div>

    <div class="cf-card cf-card--flush">
        <div class="cf-card__head">
            <h2>Invoice history</h2>
            <span class="cf-tiny cf-muted">
                Showing {{ $invoices->firstItem() ?? 0 }}–{{ $invoices->lastItem() ?? 0 }} of
                {{ $invoices->total() }}
            </span>
        </div>

        <div class="cfi-toolbar">
            <div class="cfi-chips" role="navigation" aria-label="Filter invoices by type">
                <a href="{{ route('billing.invoices', array_filter(['status' => $statusFilter])) }}"
                    @class(['cfi-chip', 'is-active' => blank($typeFilter)])>All</a>
                <a href="{{ route('billing.invoices', array_filter(['type' => 'subscription', 'status' => $statusFilter])) }}"
                    @class(['cfi-chip', 'is-active' => $typeFilter === 'subscription'])>Subscriptions</a>
                <a href="{{ route('billing.invoices', array_filter(['type' => 'sms_credits', 'status' => $statusFilter])) }}"
                    @class(['cfi-chip', 'is-active' => $typeFilter === 'sms_credits'])>SMS credits</a>
            </div>

            @if ($statuses->isNotEmpty())
                <form method="GET" action="{{ route('billing.invoices') }}" class="cfi-filters" role="search">
                    @if ($typeFilter)
                        <input type="hidden" name="type" value="{{ $typeFilter }}">
                    @endif
                    <label class="cf-tiny cf-muted" for="cfi-status">Status</label>
                    <select name="status" id="cfi-status" class="cf-input" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $option)
                            <option value="{{ $option }}" @selected($statusFilter === $option)>{{ ucfirst($option) }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        @if ($invoices->isEmpty())
            <div class="cf-empty">
                <p class="cf-empty__title">{{ $hasFilters ? 'No invoices match these filters' : 'No invoices yet' }}
                </p>
                <p class="cf-small cf-muted">
                    @if ($hasFilters)
                        Try clearing the filters to see your full invoice history.
                    @else
                        Your invoices will appear here once your subscription or SMS credits are billed.
                    @endif
                </p>
            </div>
        @else
            <div class="cf-table-wrap">
                <table class="cf-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th style="text-align:right">Amount</th>
                            <th style="text-align:right">Total</th>
                            <th style="text-align:right"><span class="cf-tiny cf-muted">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visible as $invoice)
                            <tr>
                                <td class="cf-mono cf-small" style="white-space:nowrap">
                                    #{{ str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT) }}
                                </td>
                                <td class="cf-small" style="white-space:nowrap">
                                    {{ $invoice->created_at->format('d M Y') }}</td>
                                <td>
                                    <span
                                        class="cfi-desc">{{ $invoice->description ?: $typeLabel($invoice->type) }}</span>
                                    @if ($invoice->provider_reference)
                                        <span class="cfi-ref cf-mono">ref {{ $invoice->provider_reference }}</span>
                                    @endif
                                </td>
                                <td><span class="cf-badge">{{ $typeLabel($invoice->type) }}</span></td>
                                <td><span
                                        class="cf-badge {{ $statusTone($invoice->status) }}">{{ ucfirst($invoice->status) }}</span>
                                </td>
                                <td class="cf-mono cf-small" style="text-align:right;white-space:nowrap">
                                    {{ $money($invoice->amount, $invoice->currency) }}
                                </td>
                                <td class="cf-mono" style="text-align:right;white-space:nowrap;font-weight:650">
                                    {{ $money($invoice->total(), $invoice->currency) }}
                                </td>
                                <td style="text-align:right;white-space:nowrap">
                                    <div class="cfi-row" style="justify-content:flex-end">
                                        <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-cfi-open
                                            data-invoice="{{ json_encode($documents[$invoice->id], JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                                            View
                                        </button>
                                        {{-- Print uses the same document: it opens the dialog, then
                                             the browser's print dialog takes over. --}}
                                        <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-cfi-open
                                            data-cfi-print
                                            data-invoice="{{ json_encode($documents[$invoice->id], JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                                            Print
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($invoices->hasPages())
                <div style="padding:1rem 1.35rem">{{ $invoices->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>

    {{-- ------------------------------------------------------- invoice document
         One dialog, filled from the row that was clicked. It is what gets printed
         (see @media print above) and what gets downloaded as a document file. --}}
    <div class="cfi-dialog" id="cfi-dialog" role="dialog" aria-modal="true" aria-labelledby="cfi-doc-title" hidden>
        <div class="cfi-dialog__bar">
            <button type="button" class="cf-btn cf-btn--secondary cf-btn--sm" data-cfi-download>Download PDF</button>
            <button type="button" class="cf-btn cf-btn--primary cf-btn--sm" data-cfi-print-now>Print</button>
            <button type="button" class="cf-btn cf-btn--ghost cf-btn--sm" data-cfi-close>Close ×</button>
        </div>

        <article class="cfi-doc" id="cfi-doc">
            <div class="cfi-doc__top">
                <div class="cfi-brand">
                    <span class="cfi-brand__mark" aria-hidden="true">CF</span>
                    <div>
                        <div class="cfi-brand__name">{{ config('marketing.product.name', 'ChurchFlow') }}</div>
                        <div class="cfi-brand__tag">{{ config('marketing.product.tagline', 'Church software') }}</div>
                    </div>
                </div>
                <div class="cfi-doc__title">
                    <h2 id="cfi-doc-title">Invoice</h2>
                    <div class="cfi-meta">
                        <div>Invoice no. <strong data-cfi="number">—</strong></div>
                        <div>Issued <strong data-cfi="date">—</strong></div>
                        <div data-cfi="paidRow">Paid <strong data-cfi="paid">—</strong></div>
                    </div>
                </div>
            </div>

            <div class="cfi-parties">
                <div>
                    <h4>Billed to</h4>
                    <p class="cfi-strong" data-cfi="church">—</p>
                    <p data-cfi="email">—</p>
                </div>
                <div>
                    <h4>Billed by</h4>
                    <p class="cfi-strong">{{ config('marketing.product.name', 'ChurchFlow') }}</p>
                    <p class="cf-muted">{{ config('marketing.product.tagline', '') }}</p>
                </div>
                <div>
                    <h4>Status</h4>
                    <p><span class="cfi-stamp" data-cfi="stamp">Paid</span></p>
                    <p class="cf-tiny cf-muted" data-cfi="providerWrap">Provider: <span data-cfi="provider">—</span>
                    </p>
                </div>
            </div>

            <table class="cfi-lines">
                <thead>
                    <tr>
                        <th style="width:55%">Description</th>
                        <th>Type</th>
                        <th class="cfi-num">Qty</th>
                        <th class="cfi-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong data-cfi="description">—</strong>
                            <div class="cf-tiny cf-muted cf-mono" data-cfi="reference" style="margin-top:.25rem">
                            </div>
                        </td>
                        <td data-cfi="type">—</td>
                        <td class="cfi-num">1</td>
                        <td class="cfi-num" data-cfi="amount">—</td>
                    </tr>
                </tbody>
            </table>

            <div class="cfi-totals">
                <dl>
                    <dt>Subtotal</dt>
                    <dd data-cfi="subtotal">—</dd>
                    <dt data-cfi="taxLabel">Tax</dt>
                    <dd data-cfi="tax">—</dd>
                    <dt class="cfi-due">Total</dt>
                    <dd class="cfi-due" data-cfi="total">—</dd>
                </dl>
            </div>

            <div class="cfi-doc__foot">
                <span>This invoice is a record of a charge on your ChurchFlow account.</span>
                <span data-cfi="footerRef"></span>
            </div>
        </article>
    </div>

    <script nonce="{{ \App\Http\Middleware\SecurityHeaders::nonce() }}">
        (function() {
            var dialog = document.getElementById('cfi-dialog');
            if (!dialog) return;

            var doc = document.getElementById('cfi-doc');
            var lastFocus = null;
            var current = null;

            // Plain text only: every value comes from the row payload and is
            // written with textContent, never innerHTML.
            function setField(name, value, fallback) {
                var el = doc.querySelector('[data-cfi="' + name + '"]');
                if (el) el.textContent = (value === null || value === undefined || value === '') ? (fallback || '—') :
                    value;
            }

            function fill(data) {
                current = data;
                doc.querySelector('[data-cfi="paidRow"]').style.display = data.paidAt ? '' : 'none';
                setField('number', data.number);
                setField('date', data.date);
                setField('paid', data.paidAt);
                setField('church', data.church);
                setField('email', data.email);
                setField('stamp', data.statusLabel || data.status);
                doc.querySelector('[data-cfi="providerWrap"]').style.display = data.provider ? '' : 'none';
                setField('provider', data.provider);
                setField('description', data.description);
                setField('type', data.typeLabel);
                setField('amount', data.amount);
                setField('subtotal', data.subtotal);
                setField('taxLabel', data.taxLabel || 'Tax');
                setField('tax', data.tax);
                setField('total', data.total);

                var ref = doc.querySelector('[data-cfi="reference"]');
                ref.textContent = data.providerReference ? 'Payment reference: ' + data.providerReference : '';

                var footerRef = doc.querySelector('[data-cfi="footerRef"]');
                footerRef.textContent = data.providerReference ? 'Ref ' + data.providerReference : '';

                var stamp = doc.querySelector('[data-cfi="stamp"]');
                var paid = (data.status || '') === 'paid';
                stamp.style.opacity = paid ? '1' : '.75';
            }

            function open(data) {
                lastFocus = document.activeElement;
                fill(data);
                dialog.hidden = false;
                dialog.classList.add('is-open');
                document.documentElement.classList.add('cf-navlocked');
                var closer = dialog.querySelector('[data-cfi-close]');
                if (closer) closer.focus();
            }

            function close() {
                dialog.classList.remove('is-open');
                dialog.hidden = true;
                document.documentElement.classList.remove('cf-navlocked');
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }

            function print() {
                window.print();
            }

            // PDF download.
            //
            // The browser's own print-to-PDF is used rather than shipping a PDF
            // library: it is the same engine that renders the Print button, so
            // the downloaded PDF and the printed page are identical by
            // construction, and no new dependency is added to the platform
            // (composer requires only framework + tinker; there is no PDF
            // renderer installed). The document is laid out for A4 by the
            // @media print rules above.
            //
            // If the browser exposes `print()` on its beforeprint event — Chrome
            // and Edge do — the "Save as PDF" destination can be preselected.
            // Where it is not exposed (Firefox, Safari) the print dialog opens
            // with its normal destinations, which include Save as PDF on every
            // desktop browser. Nothing is lost; the PDF is still produced.
            function download() {
                if (!current) return;

                var restored = false;

                function onBeforePrint() {
                    try {
                        var callPrint = typeof print === 'function' ? print : window.print;
                        if (typeof callPrint === 'function') callPrint();
                    } catch (e) {
                        // Older builds: no print() shortcut. The dialog stands.
                    }
                }

                function cleanup() {
                    if (restored) return;
                    restored = true;
                    window.removeEventListener('afterprint', cleanup);
                }

                window.addEventListener('beforeprint', onBeforePrint);
                window.addEventListener('afterprint', cleanup);
                window.print();
                setTimeout(cleanup, 2000);
            }

            dialog.addEventListener('click', function(event) {
                if (event.target === dialog) close();
            });
            dialog.querySelector('[data-cfi-close]').addEventListener('click', close);
            dialog.querySelector('[data-cfi-print-now]').addEventListener('click', print);
            dialog.querySelector('[data-cfi-download]').addEventListener('click', download);

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && dialog.classList.contains('is-open')) close();
            });

            document.querySelectorAll('[data-cfi-open]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var data;
                    try {
                        data = JSON.parse(button.getAttribute('data-invoice'));
                    } catch (e) {
                        return;
                    }
                    open(data);
                    if (button.hasAttribute('data-cfi-print')) {
                        // Let the document paint in the dialog before the print
                        // dialog captures it.
                        requestAnimationFrame(function() {
                            setTimeout(print, 60);
                        });
                    }
                });
            });
        })();
    </script>
</x-layout>
