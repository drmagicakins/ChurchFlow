<?php

namespace App\Traits;

use App\Observers\AuditableObserver;

/**
 * Any model that should have create/update/delete recorded in audit_logs
 * uses this trait. Keep it off high-volume, low-sensitivity models
 * (e.g. a "last seen" timestamp bump) — audit everything that matters
 * (people, money, permissions, settings), not everything that changes.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditableObserver::class);
    }
}
