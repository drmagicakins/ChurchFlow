<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'ChurchFlow'))</title>
    <style>
        :root {
            --brand: #4f46e5;
            --brand-dark: #4338ca;
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --bg: #f9fafb;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.5;
        }
        header.topbar {
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand { font-weight: 700; color: var(--brand); text-decoration: none; font-size: 1.05rem; }
        main { max-width: 56rem; margin: 0 auto; padding: 2rem 1.25rem; }
        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        h1 { font-size: 1.5rem; margin: 0 0 0.5rem; }
        h2 { font-size: 1.15rem; margin: 1.5rem 0 0.5rem; }
        p.muted, .muted { color: var(--muted); }
        label { display: block; font-weight: 600; font-size: 0.875rem; margin: 0.9rem 0 0.3rem; }
        input[type=text], input[type=email], input[type=password] {
            width: 100%;
            padding: 0.6rem 0.7rem;
            border: 1px solid var(--line);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            background: #fff;
        }
        input:focus { outline: 2px solid var(--brand); outline-offset: 0; border-color: var(--brand); }
        button {
            margin-top: 1.25rem;
            width: 100%;
            padding: 0.65rem 1rem;
            background: var(--brand);
            color: #fff;
            border: 0;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: var(--brand-dark); }
        button.inline { width: auto; margin: 0; padding: 0.4rem 0.8rem; font-size: 0.85rem; }
        .errors {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--danger);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .errors ul { margin: 0; padding-left: 1.1rem; }
        .auth-wrap { max-width: 26rem; margin: 3rem auto; }
        .alt { text-align: center; margin-top: 1rem; font-size: 0.9rem; color: var(--muted); }
        .alt a { color: var(--brand); }
        .pill {
            display: inline-block;
            background: #eef2ff;
            color: var(--brand-dark);
            border-radius: 999px;
            padding: 0.15rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        dl.facts { margin: 1rem 0 0; display: grid; grid-template-columns: max-content 1fr; gap: 0.35rem 1rem; font-size: 0.9rem; }
        dl.facts dt { color: var(--muted); }
    </style>
</head>
<body>
    @hasSection('topbar')
        <header class="topbar">
            @yield('topbar')
        </header>
    @else
        <header class="topbar">
            <a class="brand" href="{{ url('/') }}">ChurchFlow</a>
            <nav>
                @auth
                    <form method="POST" action="{{ route('logout') }}" style="display:inline">
                        @csrf
                        <button class="inline" type="submit">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" style="margin-right:1rem;color:var(--brand)">Log in</a>
                    <a href="{{ route('register') }}" style="color:var(--brand)">Register</a>
                @endauth
            </nav>
        </header>
    @endif

    <main>
        @if (session('status'))
            <div class="card" style="border-color:#bbf7d0;background:#f0fdf4;margin-bottom:1rem">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
