<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Minimal Phase 1 stub — see database/migrations/..._create_members_stub_table.php.
 * Exists to prove BelongsToTenant end-to-end; Phase 2 (§10) expands this.
 */
class Member extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes, Auditable;

    protected $fillable = ['church_id', 'full_name', 'email'];
}
