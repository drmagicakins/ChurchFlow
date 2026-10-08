<?php

namespace App\Domains\Communication\Services;

use App\Models\Member;
use App\Models\SmsCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SmsCampaignService
{
    public function __construct(
        private readonly SmsSegmentCalculator $segments,
        private readonly SmsWalletService $wallets,
    ) {}

    /**
     * Same audience-targeting shape as Announcement (§19) — see the
     * migration comment on sms_campaigns. Only members with a phone number
     * on file are ever counted or charged.
     */
    public function audienceFor(SmsCampaign $campaign): Collection
    {
        return match ($campaign->audience_type) {
            'church' => Member::query()->whereNotNull('phone')->get(),
            'branch' => Member::query()->whereNotNull('phone')
                ->where('organizational_unit_id', $campaign->organizational_unit_id)->get(),
            'department' => Member::query()->whereNotNull('phone')
                ->whereHas('departments', fn ($q) => $q->where('departments.id', $campaign->department_id))
                ->get(),
            'group' => Member::query()->whereNotNull('phone')
                ->whereHas('groups', fn ($q) => $q->where('groups.id', $campaign->group_id))
                ->get(),
            default => Member::query()->whereNotNull('phone')->whereRaw('1 = 0')->get(),
        };
    }

    /**
     * §21/§22: what the church sees BEFORE confirming — recipient count,
     * segments per message, total units, and what the balance would look
     * like afterward. Purely read-only; touches no wallet balance.
     */
    public function estimate(SmsCampaign $campaign): array
    {
        $recipients = $this->audienceFor($campaign);
        $segmentsPerMessage = $this->segments->segmentsFor($campaign->message);
        $totalUnits = $recipients->count() * $segmentsPerMessage;

        $wallet = $this->wallets->walletFor($campaign->church);

        return [
            'recipient_count' => $recipients->count(),
            'segments_per_message' => $segmentsPerMessage,
            'total_units' => $totalUnits,
            'available_units' => $wallet->balance_units,
            'remaining_after_send' => max($wallet->balance_units - $totalUnits, 0),
            'sufficient_credits' => $wallet->balance_units >= $totalUnits,
        ];
    }

    /**
     * §20/§26: confirming a campaign reserves (debits) the exact units it
     * will need and snapshots the recipient list right now, inside one
     * transaction — so the estimate the admin approved is exactly what
     * gets charged and sent, not a number that could have drifted if
     * membership changed between "estimate" and "send". Refuses outright
     * if the wallet can't cover it (§20: "Do not allow sending if there
     * are insufficient credits").
     */
    public function confirmAndQueue(SmsCampaign $campaign, User $confirmedBy): SmsCampaign
    {
        abort_unless($campaign->status === 'draft', 409, 'Only a draft campaign can be confirmed.');

        $recipients = $this->audienceFor($campaign);
        $segmentsPerMessage = $this->segments->segmentsFor($campaign->message);
        $totalUnits = $recipients->count() * $segmentsPerMessage;

        abort_if($totalUnits === 0, 422, 'No recipients with a phone number match this audience.');

        return DB::transaction(function () use ($campaign, $recipients, $segmentsPerMessage, $totalUnits) {
            $wallet = $this->wallets->walletFor($campaign->church);
            $this->wallets->debit($wallet, $totalUnits, "campaign:{$campaign->id}", "Reserved for campaign \"{$campaign->name}\"");

            foreach ($recipients as $member) {
                $campaign->recipients()->create([
                    'member_id' => $member->id,
                    'phone' => $member->phone,
                    'segments' => $segmentsPerMessage,
                    'status' => 'pending',
                ]);
            }

            $campaign->update([
                'status' => 'queued',
                'estimated_units' => $totalUnits,
                'reserved_units' => $totalUnits,
            ]);

            return $campaign->fresh();
        });
    }
}
