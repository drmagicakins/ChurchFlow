@extends('layouts.app')

@section('title', 'Register — ChurchFlow')

@section('content')
    <div class="auth-wrap">
        <div class="card">
            <h1>Create your church account</h1>
            <p class="muted">
                Phase 1 setup: this creates your church and owner account immediately.
                Payment-gated activation arrives in Phase 8.
            </p>

            @if ($errors->any())
                <div class="errors" style="margin-top:1rem">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                <label for="church_name">Church name</label>
                <input id="church_name" type="text" name="church_name" value="{{ old('church_name') }}" required autofocus>

                <label for="name">Your name</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required
                    autocomplete="name">

                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                    autocomplete="username">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">

                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                    autocomplete="new-password">

                <button type="submit">Create account</button>
            </form>
        </div>

        <p class="alt">
            Already registered? <a href="{{ route('login') }}">Log in</a>
        </p>
    </div>
@endsection
