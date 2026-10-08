# Deployment

## Register the new middleware

Laravel 11+ registers global/group middleware in `bootstrap/app.php`. Merge:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
})
```

If this project predates that structure, add the same line to
`App\Http\Kernel::$middleware` instead.

## One-time release steps

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Zero-downtime notes:
- Run migrations BEFORE swapping traffic to the new release — every
  migration in this codebase is additive (new tables/columns) or guarded,
  never a blind drop/rename of something the previous release still reads.
  The one exception needing care: `database/migrations/*_add_details_to_members_table.php`-style
  additive migrations are safe to run while the old release is still live;
  a future migration that ever removes a column the running release still
  queries is not, and needs an expand/contract (add → deploy → stop using
  → remove in a later release) split instead.
- `php artisan down --refresh=15` / `php artisan up` if your deploy process
  doesn't do connection draining itself.

## Processes a production deployment needs running

| Process | Command | Why |
|---|---|---|
| Web (PHP-FPM behind nginx) | `php-fpm` | Serves requests |
| Queue worker | `php artisan queue:work --tries=3` | SMS campaigns, announcement emails, the Subvention→Communication listener, webhook processing side-effects — none of these are synchronous |
| Scheduler | `php artisan schedule:work` (or cron + `schedule:run` every minute) | `subscriptions:process-billing-cycle` (daily renewals/dunning) and `backup:database` (see its own docblock) |

Run the queue worker under a process supervisor (systemd, supervisord, or
your platform's equivalent) with automatic restart — a worker that dies
silently means SMS campaigns and billing emails silently stop sending.
`--max-time=3600` (set in `docker-compose.yml`) makes the worker recycle
itself hourly, which also picks up new code after a deploy without a
manual restart.

## Environment

- Copy `.env.example`, fill in real values, **never commit the result**
  (§43). `PAYMENT_WEBHOOK_SECRET` and `SMS_PRICE_PER_UNIT` unset means
  those features fail closed (webhook 503s, SMS sales refused) rather than
  running with an invented default — that's intentional, not a bug to
  "fix" by inventing one.
- `QUEUE_CONNECTION` should be `redis` or `database` in production, never
  `sync` — `sync` makes every queued job in this app (Phase 7's SMS/email
  jobs, Phase 8's webhook-triggered side effects) run inline on the
  request that dispatched it, defeating the entire reason they're jobs.

## Health checks

Point your load balancer / uptime monitor at `GET /health` (added in Phase
10, `HealthController`) rather than Laravel's default `/up` — `/up` only
proves the PHP process answers; `/health` actually probes the database,
cache, and queue backend independently and returns 503 if any of them is
down, with per-dependency detail in the JSON body.

## Database backups

`php artisan backup:database` (scheduled daily at 02:00 in
`routes/console.php`) is a **minimal** mysqldump-to-local-disk command —
read its own docblock. For real production use, replace it with either
your managed database provider's built-in backups (point-in-time recovery,
which this script cannot do) or `spatie/laravel-backup` configured to ship
to off-site storage (S3 in another region, at minimum). Whatever you use,
actually test a restore before you need one for real.

## Performance notes already built in, and what's still manual

Already in the codebase from earlier phases: composite indexes on every
tenant-scoped table's hot query path (`church_id` + whatever it's commonly
filtered/sorted by — see the migrations), chunked queries for anything
that could scale with a church's membership (`ExportMembersToCsv`,
`SendAnnouncementEmailsJob`), and queued jobs for anything that shouldn't
block a request.

Not yet done, and genuinely needs real traffic/query-log data to do well
rather than guessing: Redis-backed response/query caching for read-heavy
dashboard endpoints, and a review of N+1 queries across list views once
real usage patterns exist (the placeholder views in this scaffold are
simple enough that this hasn't mattered yet — it will, once they're
replaced with the real UI).

## Docker

`docker-compose.yml` is a development/reference setup, not a production
manifest — see the comments at its top for what changes for real
production (managed database instead of a `db` container, secrets from a
vault, horizontal scaling). `docker/Dockerfile` builds one image used by
three different services (`app`, `queue-worker`, `scheduler`) running
different commands against it, so they can never drift out of sync with
each other's code — only the command differs, never the build.
