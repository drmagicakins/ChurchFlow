<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SupportTicketReply extends Model
{
    public $timestamps = false; // created_at only, via useCurrent()

    protected $fillable = ['support_ticket_id', 'author_id', 'body', 'is_internal_note'];

    protected function casts(): array
    {
        return ['is_internal_note' => 'boolean', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('viaTicketTenant', function (Builder $builder) {
            if (app()->bound('tenant.disabled') && app('tenant.disabled') === true) {
                return;
            }

            $churchId = app()->bound('tenant.church_id') ? app('tenant.church_id') : null;

            if ($churchId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas(
                'ticket',
                fn ($q) => $q->withoutGlobalScopes()->where('church_id', $churchId)
            );
        });
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
