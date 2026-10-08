<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'name', 'description'];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'group_member')->withTimestamps();
    }
}
