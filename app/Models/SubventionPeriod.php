<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubventionPeriod extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['church_id', 'name', 'period_start', 'period_end', 'status'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date'];
    }

    public function submissions()
    {
        return $this->hasMany(SubventionSubmission::class);
    }
}
