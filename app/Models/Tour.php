<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Platform-wide content, not tenant-scoped — every church sees the same tour catalog. */
class Tour extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'feature', 'version', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function steps()
    {
        return $this->hasMany(TourStep::class)->where('is_enabled', true)->orderBy('sort_order');
    }

    public function progressFor(User $user)
    {
        return UserTourProgress::firstOrNew(['user_id' => $user->id, 'tour_id' => $this->id]);
    }
}
