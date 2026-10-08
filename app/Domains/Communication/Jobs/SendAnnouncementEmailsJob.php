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

        Member::withoutGlobalScopes()
            ->where('church_id', $announcement->church_id)
            ->whereNotNull('email')
            ->chunkById(200, function ($members) use ($announcement) {
                foreach ($members as $member) {
                    if ($announcement->isVisibleToMember($member)) {
                        Mail::to($member->email)->queue(new AnnouncementMail($announcement));
                    }
                }
            });
    }
}
