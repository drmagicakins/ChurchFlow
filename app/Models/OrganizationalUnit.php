<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationalUnit extends Model
{
    use BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'unit_type_id', 'parent_id', 'name', 'code'];

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }

    public function parent()
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }
}
