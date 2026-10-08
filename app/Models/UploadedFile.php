<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    use BelongsToTenant, Auditable;

    protected $fillable = [
        'church_id', 'uploaded_by', 'attachable_type', 'attachable_id',
        'disk_path', 'original_filename', 'mime_type', 'size_bytes', 'visibility',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** A time-limited link — never a bare public path to the file (§23). */
    public function signedUrl(int $expiresInMinutes = 30): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'files.show',
            now()->addMinutes($expiresInMinutes),
            ['uploadedFile' => $this->id],
        );
    }
}
