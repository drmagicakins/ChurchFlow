<?php

namespace App\Mail;

use App\Mail\Concerns\BrandedAsChurch;
use App\Models\Announcement;
use App\Models\Church;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A church announcement, sent to the members it targets.
 *
 * BRANDED AND ANSWERABLE. It renders in the church's own email frame (logo,
 * signature — see emails/layouts/church) and carries a Reply-To naming the
 * church owner and the parish pastor, so a member who answers reaches a person
 * rather than the platform. The body, subject and recipients are unchanged:
 * this is the church's message, only presented as the church.
 */
class AnnouncementMail extends Mailable
{
    use BrandedAsChurch, Queueable, SerializesModels;

    /**
     * @param  Church|null  $church  the church as the sender; null only for a
     *                               legacy caller, in which case the message is
     *                               sent exactly as before (no branding).
     */
    public function __construct(
        public readonly Announcement $announcement,
        public readonly ?Church $church = null,
    ) {}

    protected function brandedChurch(): ?Church
    {
        // A branch announcement answers to THAT branch's pastor; a church-wide
        // one answers to every pastor, which is the resolver's default.
        return $this->church ?? $this->announcement->church;
    }

    protected function brandedParishId(): ?int
    {
        // A department/group announcement is filed against the parish that owns
        // it; a church-wide one has none (null).
        return $this->announcement->organizational_unit_id ? (int) $this->announcement->organizational_unit_id : null;
    }

    public function build(): self
    {
        return $this->subject($this->announcement->title)
            ->view('emails.announcement', $this->churchBranding([
                'announcement' => $this->announcement,
            ]));
    }
}
