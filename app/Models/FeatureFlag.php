<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = ['key', 'label', 'description', 'is_globally_enabled'];

    protected $casts = ['is_globally_enabled' => 'boolean'];

    public function churchOverrides()
    {
        return $this->hasMany(FeatureFlagChurch::class);
    }
}
