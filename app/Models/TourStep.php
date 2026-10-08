<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TourStep extends Model
{
    protected $fillable = [
        'tour_id', 'target_selector', 'title', 'description',
        'position', 'sort_order', 'action_url', 'image_url', 'is_enabled',
    ];

    protected $casts = ['is_enabled' => 'boolean'];

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }
}
