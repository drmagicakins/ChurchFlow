# ChurchFlow — How to Run the App

Stack: Laravel 12 (PHP 8.4) + Vite 7 + Tailwind 4 + Alpine.js + SQLite.

## Prerequisites

- PHP 8.2+ (this machine has 8.4.14)
- Composer
- Node.js + npm
- `pcntl` is NOT available on Windows — see the note about Pail below.

## One-time setup

Run this only when the project is freshly cloned (it installs deps, creates
`.env`, generates the app key, migrates, and builds front-end assets):

```powershell
composer run setup
```

## Daily development — the normal way

```powershell
composer run dev
```

This starts four things together via `concurrently`:

| Name   | Command                              | Purpose                |
| ------ | ------------------------------------ | ---------------------- |
| server | `php artisan serve`                  | App at :8000           |
| queue  | `php artisan queue:listen --tries=1` | Background jobs        |
| logs   | `php artisan pail --timeout=0`       | Live log tail          |
| vite   | `npm run dev`                        | HMR front-end at :5173 |

### Windows caveat

`composer run dev` fails on Windows because **Pail requires the `pcntl`
extension**, which is not available on Windows. The failure kills the whole
`concurrently` group, so nothing runs.

Run the pieces yourself instead, each in its own terminal:

```powershell
# Terminal 1 — web server (http://localhost:8000)
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — front-end with hot reload (http://localhost:5173)
npm run dev

# Terminal 3 — queue worker (needed for queued jobs/notifications)
php artisan queue:listen --tries=1
```

Or launch just the two essential ones in the background from one shell:

```powershell
Start-Process php -ArgumentList "artisan serve --host=127.0.0.1 --port=8000"
Start-Process npm -ArgumentList "run dev"
```

Then open **http://localhost:8000** (Laravel serves the page and proxies to
Vite for assets).

### If port 8000 is already in use

The previous dev process may still be alive. Check and free it:

```powershell
# What is listening on 8000?
Get-NetTCPConnection -LocalPort 8000 -State Listen

# Kill the owning process (replace <PID>)
Stop-Process -Id <PID> -Force
```

Or serve on a different port: `php artisan serve --port=8001`.

## Production-style run

```powershell
npm run build          # compile front-end assets
php artisan serve      # or point your web server at public/
```

## Database

SQLite, file at `database/database.sqlite`.

```powershell
php artisan migrate            # apply pending migrations
php artisan migrate:status     # show what has run
php artisan migrate:fresh --seed   # wipe + rebuild + seed demo data (destructive)
php artisan db:seed            # seed without wiping
```

## Tests

```powershell
composer run test              # clears config then runs the suite
php artisan test               # same, without the config clear
php artisan test --filter=SomeTest
```

## Useful commands

```powershell
php artisan route:list         # all registered routes
php artisan about              # environment / driver summary
php artisan config:clear       # clear cached config
php artisan cache:clear
php artisan optimize:clear     # clear all caches at once
php artisan tinker             # REPL
```

## Optional env settings

Left blank on purpose in `.env`; the app runs fine without them (dev uses the
Null SMS provider and the Null payment gateway). Fill these in to enable the
real integrations:

- `PAYMENT_WEBHOOK_SECRET` — unset ⇒ webhook endpoint rejects everything (fails closed)
- `SMS_PRICE_PER_UNIT` — unset ⇒ selling SMS credits is refused
- `PAYSTACK_SECRET_KEY` / `FLUTTERWAVE_SECRET_KEY` — real gateway credentials
- `TERMII_API_KEY` / `TERMII_SENDER_ID` — real SMS provider

## Health check

```powershell
php artisan serve
# then visit http://localhost:8000/health        (human-readable page)
# or     http://localhost:8000/health.json       (503 when degraded)
```
