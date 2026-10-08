<?php

namespace App\Domains\Files\Services;

use App\Models\Church;
use App\Models\UploadedFile as UploadedFileModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * §43's "never trust uploaded file extensions" and §23's "secure file
 * uploads" implemented concretely, not just stated as a principle:
 *
 *  1. The MIME type is SNIFFED from the file's actual bytes
 *     (UploadedFile::getMimeType() uses fileinfo against content, not the
 *     client-supplied Content-Type header or filename extension) and
 *     checked against an explicit allow-list per use case.
 *  2. The on-disk filename is a random token, never the client's filename
 *     or anything derived from it — so even a file that passed MIME
 *     validation can't be used to guess or collide with another tenant's
 *     path, and a misconfigured server serving the storage disk directly
 *     can't be browsed/enumerated.
 *  3. Stored on the 'private' disk (configure this to a location outside
 *     the public webroot — see config/filesystems.php) by default; a
 *     'public' visibility file is still served through a signed,
 *     time-limited URL (FileDownloadController), never a bare public path,
 *     so access can be revoked and every access is logged.
 *  4. The original filename is kept ONLY for display/download-as — never
 *     used to build a path or passed to any shell/filesystem call.
 */
class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
    ];

    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB — generous for a profile/cover photo, not a document vault

    public function storeImage(
        UploadedFile $file,
        Church $church,
        User $uploadedBy,
        ?Model $attachable = null,
        string $visibility = 'private',
    ): UploadedFileModel {
        abort_unless($file->isValid(), 422, 'The uploaded file is corrupted or incomplete.');
        abort_if($file->getSize() > self::MAX_SIZE_BYTES, 422, 'File is too large (max 5MB).');

        $sniffedMime = $file->getMimeType(); // content-sniffed, not client-supplied
        abort_unless(
            in_array($sniffedMime, self::ALLOWED_MIME_TYPES, true),
            422,
            'That file type is not allowed. Upload a JPEG, PNG, WebP, or GIF image.'
        );

        $extension = match ($sniffedMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        };

        // Random token, not the client's filename — see class docblock.
        $diskPath = "churches/{$church->id}/".Str::uuid().'.'.$extension;

        Storage::disk('private')->put($diskPath, file_get_contents($file->getRealPath()));

        return UploadedFileModel::create([
            'church_id' => $church->id,
            'uploaded_by' => $uploadedBy->id,
            'attachable_type' => $attachable?->getMorphClass(),
            'attachable_id' => $attachable?->getKey(),
            'disk_path' => $diskPath,
            'original_filename' => $file->getClientOriginalName(), // display only — see docblock
            'mime_type' => $sniffedMime,
            'size_bytes' => $file->getSize(),
            'visibility' => $visibility,
        ]);
    }

    public function delete(UploadedFileModel $uploadedFile): void
    {
        Storage::disk('private')->delete($uploadedFile->disk_path);
        $uploadedFile->delete();
    }
}
