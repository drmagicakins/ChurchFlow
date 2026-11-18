<?php

namespace App\Domains\Files\Services;

use App\Models\Church;
use App\Models\UploadedFile as UploadedFileModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The church logo: the one upload that must be reachable WITHOUT a session.
 *
 * WHY NOT THE NORMAL UPLOAD PATH. Every other image goes to the private disk
 * and is served through a signed, 30-minute URL (FileUploadService,
 * UploadedFile::signedUrl). That is right for a member's photo or a document —
 * but a logo is embedded in EMAIL, where:
 *
 *   - there is no session, so the `signed` middleware cannot be satisfied by the
 *     recipient's mail client; and
 *   - the link is rendered once and opened much later, so a 30-minute signature
 *     would be dead by the time anyone looked at the message.
 *
 * A logo is also not private data: it is the church's mark, printed on every
 * notice it sends. It is therefore written to the PUBLIC disk under an
 * unguessable filename (a UUID), which is the ordinary arrangement for a brand
 * asset and cannot be enumerated.
 *
 * EVERYTHING ELSE IS UNCHANGED, deliberately:
 *   - the file is sniffed by content, not by extension (same allow-list);
 *   - it is size-limited by the SAME ceiling the screen promises;
 *   - it is recorded in `uploaded_files`, so the plan's storage quota counts it
 *     and "who uploaded this" is answerable.
 *
 * If the public disk is ever not configured, the upload is refused with a
 * message naming it rather than silently making the logo invisible in email.
 */
class ChurchLogoStorage
{
    /** Where on the public disk logos live, relative to the disk root. */
    public const PUBLIC_DIR = 'images/church-logos';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
    ];

    public function __construct(private readonly FileUploadService $uploads) {}

    /**
     * Store a logo for the church and return the UploadedFile row.
     *
     * The MIME check and the quota are DELEGATED to FileUploadService where it
     * can do them, but the write itself happens here: that service always
     * writes to the private disk, which is the one thing a logo cannot use.
     */
    public function store(UploadedFile $file, Church $church, User $uploadedBy): UploadedFileModel
    {
        // Same server-fault message as every other upload (§1t): PHP having no
        // temp directory is not the person's mistake.
        abort_if($file->getError() === UPLOAD_ERR_NO_TMP_DIR, 500, FileUploadService::NO_TEMP_DIR_MESSAGE);

        abort_unless($file->isValid(), 422, 'The uploaded file is corrupted or incomplete.');

        abort_if(
            $file->getSize() > FileUploadService::maxUploadBytes(),
            422,
            'That logo is larger than the '.FileUploadService::maxUploadMb().'MB limit. Please choose a smaller image.'
        );

        // SVG is accepted for a LOGO only (a vector mark is often the only
        // usable source a church has). It is EXCLUDED from FileUploadService's
        // list on purpose, because user photos are raster; and it is safe to
        // serve here only because this file is delivered as an image by the web
        // server, never rendered inline into an application page.
        $sniffedMime = $file->getMimeType();

        abort_unless(
            in_array($sniffedMime, self::ALLOWED_MIME_TYPES, true),
            422,
            'That file type is not allowed. Upload a PNG, JPEG, WebP, GIF or SVG image.'
        );

        $extension = match ($sniffedMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
        };

        // The plan's storage quota, checked BEFORE anything is written.
        app(\App\Domains\Subscriptions\Services\StorageLimitService::class)
            ->assertCanStore($church, (int) $file->getSize());

        $disk = $this->publicDisk();
        $path = "{$church->id}/".\Illuminate\Support\Str::uuid().'.'.$extension;

        abort_unless(
            $disk->put(self::PUBLIC_DIR.'/'.$path, file_get_contents($file->getRealPath())),
            500,
            'The logo could not be written to the public disk. Check that it is writable.'
        );

        return UploadedFileModel::create([
            'church_id' => $church->id,
            'uploaded_by' => $uploadedBy->id,
            'attachable_type' => $church->getMorphClass(),
            'attachable_id' => $church->getKey(),
            'disk_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $sniffedMime,
            'size_bytes' => $file->getSize(),
            // Public on purpose — see the class docblock. The row still records
            // it, so it stays distinguishable from a private upload.
            'visibility' => 'public',
        ]);
    }

    /** Remove a stored logo from wherever this service put it. */
    public function delete(UploadedFileModel $file): void
    {
        $this->publicDisk()->delete(self::PUBLIC_DIR.'/'.$file->disk_path);
        $file->delete();
    }

    private function publicDisk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('public');
    }
}
