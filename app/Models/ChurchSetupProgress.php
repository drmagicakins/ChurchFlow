<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ChurchSetupProgress extends Model
{
    use BelongsToTenant;

    protected $fillable = ['church_id', 'step_key', 'completed_at', 'completed_by'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
