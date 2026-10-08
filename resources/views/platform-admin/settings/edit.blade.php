<x-layout>
    <h1>Platform Settings</h1>
    <form method="POST" action="{{ route('platform-admin.settings.update') }}">@csrf
        <label>SMS price per unit: <input type="number" step="0.01" name="sms_price_per_unit" value="{{ $smsPricePerUnit }}"></label>
        <label>Default tax rate (%): <input type="number" step="0.01" name="default_tax_rate" value="{{ $defaultTaxRate }}"></label>
        <button>Save</button>
    </form>
</x-layout>
