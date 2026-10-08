<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * No church_id column on this table (it belongs to a member, which
 * already belongs to a church), so it can't use the standard
 * BelongsToTenant trait. Instead it scopes itself via a join back to
 * members, which IS tenant-scoped — this keeps a direct
 * MemberCustomFieldValue::find($id) just as safe as going through
 * $member->customFieldValues().
 */
class MemberCustomFieldValue extends Model
{
    protected $fillable = ['member_id', 'member_custom_field_definition_id', 'value'];

    protected static function booted(): void
    {
        static::addGlobalScope('viaMemberTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas('member', fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId));
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function definition()
    {
        return $this->belongsTo(MemberCustomFieldDefinition::class, 'member_custom_field_definition_id');
    }
}
