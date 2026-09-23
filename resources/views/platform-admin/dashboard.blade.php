@extends('layouts.app')

@section('title', 'Platform Admin — ChurchFlow')

@section('content')
    <div class="card">
        <span class="pill">Platform Administration</span>
        <h1 style="margin-top:0.75rem">Platform dashboard</h1>
        <p class="muted">
            This area runs with tenancy explicitly <strong>disabled</strong>
            (<code>tenant.disabled = true</code>), so it can see across every church.
            Church-facing routes never reach here.
        </p>

        <h2>Signed in as</h2>
        <dl class="facts">
            <dt>Name</dt>
            <dd>{{ auth()->user()->name }}</dd>
            <dt>Email</dt>
            <dd>{{ auth()->user()->email }}</dd>
            <dt>Church context</dt>
            <dd>{{ auth()->user()->church_id === null ? 'None (by design)' : auth()->user()->church_id }}</dd>
        </dl>
    </div>
@endsection
