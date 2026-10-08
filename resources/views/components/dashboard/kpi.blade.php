@props(['label', 'value', 'icon', 'tone' => 'blue', 'pct' => null, 'invert' => false])
<div class="cfd-panel cfd-kpi">
    <span class="cfd-ico cfd-ico--{{ $tone }}"><x-ui.icon :name="$icon" /></span>
    <div>
        <p class="cfd-kpi__label">{{ $label }}</p>
        <p class="cfd-kpi__value">{{ $value }}</p>
        <x-dashboard.trend :pct="$pct" :invert="$invert" />
    </div>
</div>
