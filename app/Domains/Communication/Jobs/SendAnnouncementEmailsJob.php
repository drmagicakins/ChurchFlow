<?php

namespace App\Domains\Communication\Jobs;

use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * §16/§19: email is included with the subscription, unlike SMS — so unlike
 * SendSmsCampaignJob, there is deliberately no wallet, no per-recipient
 * cost, and no confirm-and-reserve step here. It still runs as a queued
 * job rather than synchronously in the request, purely for the same
 * "don't time out the browser request" reason (§24), not a billing one.
 */
class SendAnnouncementEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::withoutGlobalScopes()->findOrFail($this->announcementId);

        // The church is loaded ONCE for the whole batch: every message needs it
        // for its logo, signature and Reply-To, and reading it per recipient
        // would be one extra query per member.
        $church = \App\Models\Church::query()->find($announcement->church_id);

        Member::withoutGlobalScopes()
            ->where('church_id', $announcement->church_id)
            ->whereNotNull('email')
            ->chunkById(200, function ($members) use ($announcement, $church) {
                foreach ($members as $member) {
                    if ($announcement->isVisibleToMember($member)) {
                        Mail::to($member->email)->queue(new AnnouncementMail($announcement, $church));
                    }
                }
            });
    }
}
