<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    protected $fillable = ['attendance_session_id', 'member_id', 'status', 'recorded_by'];

    protected static function booted(): void
    {
        static::addGlobalScope('viaSessionTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'attendanceSession',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function attendanceSession()
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
