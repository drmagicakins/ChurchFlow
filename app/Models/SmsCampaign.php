<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCampaign extends Model
{
    use HasFactory, BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'name', 'message', 'audience_type',
        'organizational_unit_id', 'department_id', 'group_id',
        'status', 'estimated_units', 'reserved_units', 'created_by', 'scheduled_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function recipients()
    {
        return $this->hasMany(SmsCampaignRecipient::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
