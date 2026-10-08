<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform-wide, not tenant-scoped — deliberately no BelongsToTenant. */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];
}
