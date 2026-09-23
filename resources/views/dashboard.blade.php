@extends('layouts.app')

@section('title', 'Dashboard — ChurchFlow')

@section('content')
    <div class="card">
        <span class="pill">Phase 1 · Foundation</span>
        <h1 style="margin-top:0.75rem">Welcome, {{ auth()->user()->name }}</h1>
        <p class="muted">
            You are signed in to <strong>{{ auth()->user()->church?->name }}</strong>.
            Every query in this request is automatically scoped to your church by the
            global <code>TenantScope</code>.
        </p>

        <h2>Tenant context</h2>
        <dl class="facts">
            <dt>Church ID</dt>
            <dd>{{ auth()->user()->church_id }}</dd>
            <dt>Church status</dt>
            <dd>{{ auth()->user()->church?->status ?? '—' }}</dd>
            <dt>Your roles</dt>
            <dd>
                @forelse (auth()->user()->roles as $role)
                    {{ $role->name }}@if (!$loop->last)
                        ,
                    @endif
                    @empty
                        —
                    @endforelse
                </dd>
                <dt>Platform admin</dt>
                <dd>{{ auth()->user()->is_platform_admin ? 'Yes' : 'No' }}</dd>
            </dl>
        </div>

        <div class="card" style="margin-top:1.25rem">
            <h2 style="margin-top:0">What's next</h2>
            <p class="muted" style="margin-bottom:0">
                Phase 2 (Church &amp; People) adds the full organizational tree, member profiles,
                families and departments on top of this foundation. The <code>BelongsToTenant</code>
                trait is what every one of those models will use — no shape changes required here.
            </p>
        </div>
    @endsection
