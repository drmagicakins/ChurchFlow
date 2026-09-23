<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Church extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'denomination', 'timezone', 'currency', 'status', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function organizationalUnits()
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function unitTypes()
    {
        return $this->hasMany(UnitType::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    /** Terminology override helper, e.g. $church->label('province') => "Diocese" */
    public function label(string $key, string $default): string
    {
        return $this->settings['terminology'][$key] ?? $default;
    }
}
