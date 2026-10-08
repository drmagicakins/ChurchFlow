<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Family extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'name'];

    public function members()
    {
        return $this->belongsToMany(Member::class, 'family_member')
            ->withPivot('relationship')
            ->withTimestamps();
    }

    public function head()
    {
        return $this->members()->wherePivot('relationship', 'head')->first();
    }
}
