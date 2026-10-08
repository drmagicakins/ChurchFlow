<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubventionRuleSet extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function rules()
    {
        return $this->hasMany(SubventionRule::class)->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(SubventionSubmission::class);
    }
}
