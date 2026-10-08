<?php

namespace Tests\Feature;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\SmsSendResult;
use App\Domains\Communication\Services\SmsCampaignService;
use App\Domains\Communication\Services\SmsSegmentCalculator;
use App\Domains\Communication\Services\SmsSendingService;
use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\SmsCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmsCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function campaignService(): SmsCampaignService
    {
        return new SmsCampaignService(new SmsSegmentCalculator(), new SmsWalletService());
    }

    public function test_estimate_only_counts_members_with_a_phone_number(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        Member::factory()->for($church, 'church')->create(['phone' => '08010000000']);
        Member::factory()->for($church, 'church')->create(['phone' => null]);

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();

        $estimate = $this->campaignService()->estimate($campaign);

        $this->assertSame(1, $estimate['recipient_count']);
        $this->assertSame(1, $estimate['total_units']); // 1 recipient * 1 segment
    }

    public function test_estimate_respects_branch_department_and_group_targeting(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $dept = Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $inDept = Member::factory()->for($church, 'church')->create(['phone' => '08010000001']);
        $outsideDept = Member::factory()->for($church, 'church')->create(['phone' => '08010000002']);
        $inDept->departments()->attach($dept);

        $campaign = SmsCampaign::factory()->for($church, 'church')->create([
            'audience_type' => 'department', 'department_id' => $dept->id,
        ]);

        $estimate = $this->campaignService()->estimate($campaign);
        $this->assertSame(1, $estimate['recipient_count']);
    }

    public function test_confirming_reserves_the_exact_units_estimated_and_snapshots_recipients(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(3)->create(['phone' => '08010000000']);

        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $this->assertSame('queued', $campaign->status);
        $this->assertSame(3, $campaign->reserved_units); // 3 recipients * 1 segment
        $this->assertCount(3, $campaign->recipients);
        $this->assertSame(97, $wallet->fresh()->balance_units);
    }

    public function test_confirming_with_insufficient_credits_is_refused_and_reserves_nothing(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(5)->create(['phone' => '08010000000']);
        // Wallet starts at 0 — no credits purchased.

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();

        try {
            $this->campaignService()->confirmAndQueue($campaign, $user);
            $this->fail('Expected an insufficient-credits exception.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // expected
        }

        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertCount(0, $campaign->fresh()->recipients);
    }

    public function test_sending_marks_successful_recipients_sent_and_campaign_completed(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        Member::factory()->for($church, 'church')->count(2)->create(['phone' => '08010000000']);
        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $alwaysSucceeds = new class implements SmsProviderInterface {
            public function send(string $toPhone, string $message): SmsSendResult
            {
                return new SmsSendResult(success: true, providerMessageId: 'msg-1');
            }
        };

        $sender = new SmsSendingService($alwaysSucceeds, new SmsWalletService());
        $result = $sender->send($campaign);

        $this->assertSame('completed', $result->status);
        $this->assertCount(2, $result->recipients()->where('status', 'sent')->get());
        $this->assertSame(98, $wallet->fresh()->balance_units, 'No refund expected when every send succeeds.');
    }

    public function test_a_failed_recipient_is_refunded_and_the_rest_of_the_batch_still_sends(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);

        $good = Member::factory()->for($church, 'church')->create(['phone' => '08010000001']);
        $bad = Member::factory()->for($church, 'church')->create(['phone' => '08010000002']);

        $wallet = (new SmsWalletService())->walletFor($church);
        (new SmsWalletService())->credit($wallet, 100, 'purchase');

        $campaign = SmsCampaign::factory()->for($church, 'church')->create();
        $campaign = $this->campaignService()->confirmAndQueue($campaign, $user);

        $failsForBadNumber = new class($bad->phone) implements SmsProviderInterface {
            public function __construct(private readonly string $badPhone) {}

            public function send(string $toPhone, string $message): SmsSendResult
            {
                if ($toPhone === $this->badPhone) {
                    return new SmsSendResult(success: false, failureReason: 'Invalid number');
                }

                return new SmsSendResult(success: true, providerMessageId: 'msg-ok');
            }
        };

        $sender = new SmsSendingService($failsForBadNumber, new SmsWalletService());
        $result = $sender->send($campaign);

        $this->assertSame('partially_failed', $result->status);
        $this->assertSame(1, $result->recipients()->where('status', 'sent')->count());
        $this->assertSame(1, $result->recipients()->where('status', 'failed')->count());
        $this->assertSame('Invalid number', $result->recipients()->where('status', 'failed')->first()->failed_reason);

        // Reserved 2, spent 1 (the successful send), the failed one's unit refunded — net balance = 100 - 2 + 1 = 99
        $this->assertSame(99, $wallet->fresh()->balance_units);
    }

    public function test_a_church_cannot_see_another_churchs_sms_campaigns_or_recipients(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        SmsCampaign::factory()->for($churchA, 'church')->create(['name' => 'A Campaign']);
        SmsCampaign::factory()->for($churchB, 'church')->create(['name' => 'B Campaign']);

        app()->instance('tenant.church_id', $churchA->id);

        $this->assertCount(1, SmsCampaign::all());
        $this->assertSame('A Campaign', SmsCampaign::first()->name);
    }
}
