<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlagChurch extends Model
{
    /**
     * The migration creates the singular table name `feature_flag_church`,
     * but Eloquent would pluralise the class to `feature_flag_churches`. The
     * table is named explicitly here so the model and the schema agree —
     * without this every per-church override query threw "no such table".
     */
    protected $table = 'feature_flag_church';

    protected $fillable = ['feature_flag_id', 'church_id', 'is_enabled'];

    protected $casts = ['is_enabled' => 'boolean'];

    public function featureFlag()
    {
        return $this->belongsTo(FeatureFlag::class);
    }

    public function church()
    {
        return $this->belongsTo(Church::class);
    }
}
