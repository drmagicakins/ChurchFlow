<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PastoralCaseNote extends Model
{
    public $timestamps = false; // created_at only, via useCurrent()

    protected $fillable = ['pastoral_case_id', 'author_id', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    // Deliberately no update()/delete() path exposed anywhere in the
    // controllers for this model — see the migration comment. A note, once
    // written, stays written; corrections are a new note.

    protected static function booted(): void
    {
        static::addGlobalScope('viaCaseTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'pastoralCase',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function pastoralCase()
    {
        return $this->belongsTo(PastoralCase::class, 'pastoral_case_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
