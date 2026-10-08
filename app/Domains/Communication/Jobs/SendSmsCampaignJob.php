<?php

namespace App\Domains\Communication\Jobs;

use App\Domains\Communication\Services\SmsSendingService;
use App\Models\SmsCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $campaignId) {}

    public function handle(SmsSendingService $sender): void
    {
        // withoutGlobalScopes: a queue worker runs outside any HTTP
        // request, so no tenant is bound in this process — the campaign
        // was already tenant-verified when it was confirmed and queued.
        $campaign = SmsCampaign::withoutGlobalScopes()->findOrFail($this->campaignId);

        $sender->send($campaign);
    }
}
