<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Church extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'logo_upload_id', 'denomination', 'email', 'reply_to_email',
        'phone', 'website', 'address', 'timezone', 'currency', 'status', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * The church's logo (see FileUploadService), if one has been uploaded.
     *
     * Not tenant-scoped concerns here: `uploaded_files` is a BelongsToTenant
     * model, so the lookup in logoUrl() goes through withoutGlobalScopes() —
     * a queued email has no tenant bound, and the logo must still appear there.
     */
    public function logo()
    {
        return $this->belongsTo(UploadedFile::class, 'logo_upload_id');
    }

    public function organizationalUnits()
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function unitTypes()
    {
        return $this->hasMany(UnitType::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    /** Terminology override helper, e.g. $church->label('province') => "Diocese" */
    public function label(string $key, string $default): string
    {
        return $this->settings['terminology'][$key] ?? $default;
    }

    /**
     * The plan this church is on, if any. Used by the layout's navigation to
     * gate the Denomination-only menu entries (§14: API access, custom domain)
     * without a subscription query per menu item.
     */
    public function subscription()
    {
        return $this->hasOne(\App\Models\Subscription::class)->latestOfMany();
    }

    // --- the church profile, in the shape an email needs it -----------------

    /** The stored logo row, or null. Safe with no tenant bound (see logo()). */
    public function logoFile(): ?UploadedFile
    {
        if (! $this->logo_upload_id) {
            return null;
        }

        return $this->relationLoaded('logo')
            ? $this->logo
            : UploadedFile::withoutGlobalScopes()->find($this->logo_upload_id);
    }

    /**
     * An ABSOLUTE, public URL for the logo, or null.
     *
     * Emails cannot use the app's signed `files.show` link: a mail client has
     * no session, and the signature expires — so an image that is fine when the
     * message is sent is a broken icon an hour later. The logo is therefore
     * served from the public disk by a stable, unguessable filename
     * (see ChurchLogoStorage), which is the normal arrangement for brand assets
     * (a logo is not private data: it is printed on every notice).
     */
    public function logoUrl(): ?string
    {
        $file = $this->logoFile();

        if (! $file) {
            return null;
        }

        // Built the same way anything else on the public disk is, so a CDN or a
        // custom FILESYSTEM url applies without this method knowing about it.
        return \Illuminate\Support\Facades\Storage::disk('public')->url(
            \App\Domains\Files\Services\ChurchLogoStorage::PUBLIC_DIR.'/'.$file->disk_path
        );
    }

    /** The address a recipient's reply should go to (override first). */
    public function replyToEmail(): ?string
    {
        foreach ([$this->reply_to_email, $this->email] as $candidate) {
            if (filled($candidate) && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /** The signature block at the foot of every email this church sends. */
    public function emailSignatureLines(): array
    {
        return array_values(array_filter([
            $this->name,
            $this->address,
            $this->phone,
            $this->website,
            $this->email,
        ], fn ($line) => filled($line)));
    }
}
