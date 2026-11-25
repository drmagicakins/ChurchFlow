<?php

namespace Tests\Feature;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\SmsSendResult;
use App\Domains\Communication\Services\SmsCampaignService;
use App\Domains\Communication\Services\SmsSegmentCalculator;
use App\Domains\Communication\Services\SmsSendingService;
use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Church;
use App\Models\Member;
use App\Models\SmsCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A BROKEN SMS CONFIGURATION MUST NOT DESTROY A BATCH (2026-11-25).
 *
 * The provider refuses, by design, a send it cannot make honest sense of — no
 * key, no approved sender on the message — by THROWING before a unit is spent.
 * In both senders that throw escaped the recipient loop, which left:
 *
 *   · the remaining recipients 'pending' for ever,
 *   · their reserved units never refunded, and
 *   · the campaign stuck in 'sending'.
 *
 * These tests pin the repaired rule for BOTH paths (bulk campaign, and the
 * event-reminder job in EventSmsAudienceTest's own file is not touched):
 * every recipient is accounted for, every unit comes back, and the reason the
 * send could not happen is written on the row rather than lost in a log.
 *
 * Nothing here talks to a network: the provider is a double that throws the
 * same RuntimeException TermiiProvider throws for a misconfiguration.
 */
class SmsBatchResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function campaignService(): SmsCampaignService
    {
        return new SmsCampaignService(new SmsSegmentCalculator(), new SmsWalletService());
    }

    /** Stands in for TermiiProvider::assertConfigured() refusing the send. */
    private function refusingProvider(): SmsProviderInterface
    {
        return new class implements SmsProviderInterface
        {
            public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
            {
                throw new \RuntimeException(
                    'Termii is not configured: set TERMII_SENDER_ID in .env to one of your APPROVED sender ids.'
                );
            }
        };
    }

    public function test_a_refused_campaign_fails_every_recipient_and_refunds_every_unit(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(3)->create(['phone' => '08010000000']);

        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $this->assertSame(97, $wallet->fresh()->balance_units, 'Three units must be reserved up front.');

        $result = (new SmsSendingService($this->refusingProvider(), new SmsWalletService()))->send($campaign);

        $this->assertSame('failed', $result->status, 'A campaign nobody could be texted by is a failed campaign, not a stuck one.');
        $this->assertSame(3, $result->recipients()->where('status', 'failed')->count());
        $this->assertSame(0, $result->recipients()->where('status', 'pending')->count(), 'No recipient may be left pending.');
        $this->assertStringContainsString('TERMII_SENDER_ID', (string) $result->recipients()->first()->failed_reason);

        $this->assertSame(100, $wallet->fresh()->balance_units, 'Every reserved unit must come back.');
    }

    public function test_a_provider_that_accepts_the_first_text_and_then_refuses_does_not_strand_the_rest(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(3)->create(['phone' => '08010000000']);

        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $firstOkThenRefuses = new class implements SmsProviderInterface
        {
            private int $calls = 0;

            public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
            {
                if ($this->calls++ === 0) {
                    return new SmsSendResult(success: true, providerMessageId: 'msg-1');
                }

                throw new \RuntimeException('The provider started refusing mid-batch.');
            }
        };

        $result = (new SmsSendingService($firstOkThenRefuses, new SmsWalletService()))->send($campaign);

        $this->assertSame('partially_failed', $result->status);
        $this->assertSame(1, $result->recipients()->where('status', 'sent')->count());
        $this->assertSame(2, $result->recipients()->where('status', 'failed')->count());
        $this->assertSame(0, $result->recipients()->where('status', 'pending')->count());

        // Reserved 3, one delivered (kept), two refunded → back to 99.
        $this->assertSame(99, $wallet->fresh()->balance_units);
    }

    public function test_a_normal_batch_is_unaffected_by_the_guard(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(2)->create(['phone' => '08010000000']);

        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $alwaysSucceeds = new class implements SmsProviderInterface
        {
            public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
            {
                return new SmsSendResult(success: true, providerMessageId: 'msg-1');
            }
        };

        $result = (new SmsSendingService($alwaysSucceeds, new SmsWalletService()))->send($campaign);

        $this->assertSame('completed', $result->status);
        $this->assertSame(2, $result->recipients()->where('status', 'sent')->count());
        $this->assertSame(98, $wallet->fresh()->balance_units);
    }
}
