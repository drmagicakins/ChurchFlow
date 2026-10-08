<?php

namespace App\Domains\PlatformHealth\Services;

use App\Models\AppNotification;
use App\Models\HealthIncident;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Turns health incidents into notifications for platform admins.
 *
 * WHY THIS DE-DUPLICATES, AND HOW
 * -------------------------------
 * The obvious implementation — "for each open incident, notify the admins" —
 * is actively harmful. The check runs every five minutes, so a database that
 * is down for an hour would send twelve identical notifications to every
 * platform admin. That is how an alerting system trains people to ignore it,
 * which is worse than having no alerts at all.
 *
 * So the notification is tied to the incident's FIRST detection, not its
 * current state:
 *
 *   notifyNewIncidents()  notifies for incidents whose `notified_at` is null,
 *                         then stamps it. Notified once, ever, per incident.
 *   notifyRecovered()     notifies for incidents resolved since a cutoff that
 *                         have not yet had a recovery noticed, then stamps it.
 *
 * Two independent stamps, because an incident can be notified and then
 * recovered, and both facts are worth exactly one message each.
 *
 * The stamps live in `context` (a JSON column already on the table) rather than
 * as two more columns — this is alerting bookkeeping about a row, not part of
 * what the incident IS, and adding schema for it would make the incident table
 * harder to read for the sake of a delivery flag.
 */
class HealthIncidentNotifier
{
    /**
     * Notify platform admins about incidents that are open and have never
     * been notified. Returns how many were notified.
     */
    public function notifyNewIncidents(): int
    {
        $admins = $this->platformAdmins();

        if ($admins->isEmpty()) {
            // Worth a log line: a broken platform with nobody to tell is a
            // deployment mistake, not a quiet no-op.
            Log::warning('Health incidents are open but there is no platform admin to notify.');

            return 0;
        }

        $notified = 0;

        HealthIncident::query()
            ->open()
            ->orderByRaw("case severity when 'critical' then 0 when 'error' then 1 when 'warning' then 2 else 3 end")
            ->each(function (HealthIncident $incident) use ($admins, &$notified) {
                $context = $incident->context ?? [];
                if (! empty($context['notified_at'])) {
                    return;
                }

                foreach ($admins as $admin) {
                    $this->record(
                        $admin,
                        $this->title($incident),
                        $incident->message,
                        $this->safeUrl('health.index'),
                        'health.'.($incident->severity === 'critical' ? 'critical' : 'warning'),
                    );
                }

                $incident->update(['context' => array_merge($context, ['notified_at' => now()->toIso8601String()])]);
                $notified++;
            });

        return $notified;
    }

    /**
     * Notify platform admins about incidents that were fixed recently and have
     * not yet had a recovery notice. Returns how many were notified.
     */
    public function notifyRecoveredSince(\Illuminate\Support\Carbon $since): int
    {
        $admins = $this->platformAdmins();

        if ($admins->isEmpty()) {
            return 0;
        }

        $notified = 0;

        HealthIncident::query()
            ->resolved()
            ->where('resolved_at', '>=', $since)
            ->each(function (HealthIncident $incident) use ($admins, &$notified) {
                $context = $incident->context ?? [];
                if (! empty($context['recovery_notified_at'])) {
                    return;
                }

                foreach ($admins as $admin) {
                    $this->record(
                        $admin,
                        'Resolved: '.$this->title($incident),
                        'Recovered at '.$incident->resolved_at->toDayDateTimeString().
                            '. It was first seen at '.$incident->first_seen_at->toDayDateTimeString().
                            ' and detected '.$incident->occurrences.' time(s).',
                        $this->safeUrl('health.index'),
                        'health.resolved',
                    );
                }

                $incident->update([
                    'context' => array_merge($context, ['recovery_notified_at' => now()->toIso8601String()]),
                ]);
                $notified++;
            });

        return $notified;
    }

    /** A title that says what is broken before saying where. */
    private function title(HealthIncident $incident): string
    {
        $prefix = match ($incident->severity) {
            'critical' => 'Platform down: ',
            'error' => 'Platform issue: ',
            default => 'Platform warning: ',
        };

        return $prefix.str_replace('_', ' ', ucfirst($incident->check_name));
    }

    /**
     * Platform admins only — deliberately NOT every church owner.
     *
     * A database outage is not a church's business to triage, and telling
     * every tenant about platform infrastructure would be both alarming and
     * useless: they cannot act on it. The platform admins can.
     */
    private function platformAdmins()
    {
        return User::query()->where('is_platform_admin', true)->get();
    }

    private function record(User $user, string $title, string $body, ?string $url, string $type): void
    {
        // AppNotification is tenant-scoped by church_id, and a platform admin
        // has no church — so the scope has to stand aside for this write, and
        // church_id is explicitly null. A platform notification has no tenant,
        // which is exactly why church_id is nullable as of this phase: before
        // that, there was no row shape in which "the database is down" could be
        // delivered to the only people who can act on it.
        AppNotification::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'church_id' => $user->church_id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            // Resolved defensively. The origin of this whole service is that a
            // health problem must be reportable, so the reporting path itself
            // must never be the thing that fails — a missing route name would
            // otherwise throw and take the health check down with it, turning
            // "the queue worker stopped" into "the cron crashes". Falling back
            // to null loses a link; throwing loses the alert.
            'action_url' => $this->safeUrl($url),
        ]);
    }

    private function safeUrl(?string $routeName): ?string
    {
        if (! $routeName || ! \Illuminate\Support\Facades\Route::has($routeName)) {
            return null;
        }

        return route($routeName);
    }
}
