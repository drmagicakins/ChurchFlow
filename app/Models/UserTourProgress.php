<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTourProgress extends Model
{
    protected $fillable = [
        'user_id', 'tour_id', 'current_step', 'tour_version_seen',
        'completed_at', 'dismissed_at', 'dont_show_again',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'dont_show_again' => 'boolean',
        ];
    }

    public function tour()
    {
        return $this->belongsTo(Tour::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
