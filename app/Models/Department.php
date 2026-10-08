<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'organizational_unit_id', 'name', 'description'];

    public function organizationalUnit()
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function members()
    {
        return $this->belongsToMany(Member::class, 'department_member')
            ->withPivot('role')
            ->withTimestamps();
    }
}
