@extends('layouts.app')

@section('title', 'Log in — ChurchFlow')

@section('content')
    <div class="auth-wrap">
        <div class="card">
            <h1>Log in</h1>
            <p class="muted">Welcome back to ChurchFlow.</p>

            @if ($errors->any())
                <div class="errors" style="margin-top:1rem">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    autocomplete="username">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">

                <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;margin-top:1rem">
                    <input type="checkbox" name="remember" value="1" style="width:auto">
                    <span>Remember me</span>
                </label>

                <button type="submit">Log in</button>
            </form>
        </div>

        <p class="alt">
            No account yet? <a href="{{ route('register') }}">Create a church account</a>
        </p>
    </div>
@endsection
