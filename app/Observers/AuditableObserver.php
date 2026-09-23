<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditableObserver
{
    public function created(Model $model): void
    {
        $this->log($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'updated', $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', $model->getAttributes(), null);
    }

    private function log(Model $model, string $verb, ?array $old, ?array $new): void
    {
        // Never store raw password/secret-looking columns in the audit trail.
        $strip = fn (?array $a) => $a === null ? null : collect($a)
            ->reject(fn ($v, $k) => in_array($k, ['password', 'remember_token'], true))
            ->all();

        AuditLog::create([
            'church_id' => app()->bound('tenant.church_id') ? app('tenant.church_id') : ($model->church_id ?? null),
            'user_id' => Auth::id(),
            'action' => strtolower(class_basename($model)).'.'.$verb,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $strip($old),
            'new_values' => $strip($new),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
