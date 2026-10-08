<x-layout title="Platform Settings · ChurchFlow">
    <div class="cf-page-head">
        <div>
            <h1>Platform settings</h1>
            <p class="cf-small cf-muted" style="margin-top:.3rem">
                Runtime-editable values that apply across every church. Saved here, a change takes
                effect immediately — no deploy, and the old <code>.env</code> values only act as the
                fallback for a setting nobody has touched yet.
            </p>
        </div>
        <a href="{{ route('platform-admin.dashboard') }}" class="cf-btn cf-btn--secondary cf-btn--sm">← Overview</a>
    </div>

    @if(session('status'))
        <div class="cf-alert cf-alert--ok" role="status">
            <span class="cf-alert__icon"><x-ui.icon name="check" class="h-4 w-4" /></span>
            <div class="cf-alert__body"><p class="cf-alert__title">{{ session('status') }}</p></div>
        </div>
    @endif

    <div style="max-width:720px">
        <x-form.card
            title="Pricing and tax"
            description="These two numbers decide what every church is charged. Changing one affects new and recurring charges from the next billing cycle — it never rewrites an invoice that has already been issued."
            :action="route('platform-admin.settings.update')"
            submit="Save settings"
            submit-icon="wallet"
        >
            <x-form.input
                name="sms_price_per_unit"
                label="SMS price per unit"
                type="number"
                step="0.01"
                min="0"
                :value="$smsPricePerUnit"
                required
                hint="Charged per message segment when a church buys SMS credits. Leave unset to refuse SMS sales entirely rather than selling at an invented price."
            />

            <x-form.input
                name="default_tax_rate"
                label="Default tax rate (%)"
                type="number"
                step="0.01"
                min="0"
                max="100"
                :value="$defaultTaxRate"
                required
                hint="Applied to new checkouts and copied verbatim onto the resulting invoice, so changing it never alters what a past invoice says was charged."
            />
        </x-form.card>

        <div class="cf-card" style="margin-top:1.2rem">
            <div class="cf-card__body">
                <h2 style="margin:0 0 .4rem;font-size:.95rem">Where these are used</h2>
                <ul class="cf-small cf-muted" style="margin:0;padding-left:1.1rem;display:grid;gap:.3rem">
                    <li><strong>SMS price per unit</strong> — the SMS credit purchase flow, and every campaign cost estimate shown before a send.</li>
                    <li><strong>Default tax rate</strong> — computed once at checkout, then copied onto the invoice at activation.</li>
                    <li>Plan prices are <em>not</em> here. They live on each plan row, and are edited per plan so an individual tier can change without touching the rest.</li>
                </ul>
            </div>
        </div>
    </div>
</x-layout>
