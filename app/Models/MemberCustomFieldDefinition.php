<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MemberCustomFieldDefinition extends Model
{
    use BelongsToTenant;

    protected $fillable = ['church_id', 'key', 'label', 'type', 'options'];

    protected $casts = ['options' => 'array'];

    public function values()
    {
        return $this->hasMany(MemberCustomFieldValue::class);
    }
}
