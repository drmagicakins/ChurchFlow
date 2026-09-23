<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UnitType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['church_id', 'name', 'level'];

    public function organizationalUnits()
    {
        return $this->hasMany(OrganizationalUnit::class);
    }
}
