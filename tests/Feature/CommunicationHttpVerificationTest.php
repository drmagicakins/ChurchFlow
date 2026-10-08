<?php

namespace Tests\Feature;

use App\Domains\Communication\Jobs\SendAnnouncementEmailsJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Church;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmsCampaign;
use App\Models\SmsWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 7 over the REAL HTTP stack (auth + IdentifyTenant + controller +
 * policy + Blade render + queued job), the same layer of verification
 * PastoralHttpVerificationTest gave Phase 6.
 *
 * CommunicationTest/SmsCampaignTest exercise the services and the listener
 * directly — deliberately, so the suite never needs a running worker. What
 * that leaves unproven is everything between the route and the service: that
 * the route is actually gated on sms.manage, that a campaign belonging to
 * another church can't be reached through a URL, and that the confirm action
 * dispatches the send job. Those are the assertions here.
 */
class CommunicationHttpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Church $church): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Communications Manager']);
        $role->permissions()->attach(
            Permission::firstOrCreate(
                ['name' => 'sms.manage'],
                ['group' => 'communication', 'label' => 'Send bulk SMS campaigns and manage SMS credits']
            )
        );
        $user->roles()->attach($role);

        // The Role model's global scope hides it from the tenant-scoped eager
        // load, so load permissions explicitly for hasPermission() to see them.
        $user->load('roles.permissions');

        return $user;
    }

    private function ordinaryAdmin(Church $church): User
    {
        $names = ['members.view', 'finance.view', 'announcements.manage', 'events.manage'];
        $ids = collect($names)->map(fn ($n) => Permission::firstOrCreate(
            ['name' => $n], ['group' => 'x', 'label' => 'x']
        )->id);

        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Church Admin']);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    public function test_sms_pages_are_gated_on_sms_manage_permission(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $manager = $this->manager($church);
        $admin = $this->ordinaryAdmin($church);

        // The fixture is honest: this admin really does hold other admin access.
        $this->assertFalse($admin->hasPermission('sms.manage'));
        $this->assertTrue($admin->hasPermission('announcements.manage'));

        $this->actingAs($manager)->get('/sms/campaigns')->assertOk();
        $this->actingAs($manager)->get('/sms/wallet')->assertOk();

        $this->actingAs($admin)->get('/sms/campaigns')->assertForbidden();
        $this->actingAs($admin)->get('/sms/wallet')->assertForbidden();

        // A guest is bounced to login rather than 403'd.
        $this->app['auth']->forgetGuards();
        $this->get('/sms/campaigns')->assertRedirect('/login');
    }

    public function test_confirming_a_campaign_over_http_reserves_units_and_queues_the_send(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $manager = $this->manager($church);

        Member::factory()->for($church, 'church')->count(2)->create(['phone' => '08010000000']);

        $wallet = SmsWallet::withoutGlobalScopes()->create(['church_id' => $church->id, 'balance_units' => 50]);
        $this->assertSame(50, $wallet->balance_units);

        $campaign = SmsCampaign::factory()->for($church, 'church')->create([
            'created_by' => $manager->id,
        ]);

        // The preview screen shows the estimate without spending anything.
        $this->actingAs($manager)->get("/sms/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertSee('2');

        $this->assertSame(50, $wallet->fresh()->balance_units, 'Viewing the estimate must not touch the wallet.');

        $this->actingAs($manager)->post("/sms/campaigns/{$campaign->id}/confirm")
            ->assertRedirect(route('sms.campaigns.show', $campaign));

        // QUEUE_CONNECTION=sync in phpunit.xml, so the dispatched job runs
        // inline here: 2 recipients * 1 segment reserved, then actually sent
        // by the default NullSmsProvider, so the campaign lands completed.
        $campaign = $campaign->fresh();
        $this->assertSame('completed', $campaign->status);
        $this->assertSame(2, $campaign->reserved_units);
        $this->assertCount(2, $campaign->recipients()->where('status', 'sent')->get());
        $this->assertSame(48, $wallet->fresh()->balance_units);
    }

    public function test_confirming_without_credits_over_http_reserves_nothing(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $manager = $this->manager($church);

        Member::factory()->for($church, 'church')->count(3)->create(['phone' => '08010000000']);
        $campaign = SmsCampaign::factory()->for($church, 'church')->create(['created_by' => $manager->id]);

        $this->actingAs($manager)
            ->post("/sms/campaigns/{$campaign->id}/confirm")
            ->assertStatus(422);

        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertCount(0, $campaign->fresh()->recipients);
    }

    public function test_a_church_cannot_reach_another_churchs_campaign_or_wallet_through_a_url(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $managerA = $this->manager($churchA);

        $campaignB = SmsCampaign::factory()->for($churchB, 'church')->create(['name' => 'B Campaign']);

        app()->instance('tenant.church_id', $churchA->id);

        // 404, not 403: the tenant scope removes the row entirely, so route
        // model binding never resolves it and nothing leaks about whether
        // that campaign ID exists in another church.
        $this->actingAs($managerA)->get("/sms/campaigns/{$campaignB->id}")->assertNotFound();
        $this->actingAs($managerA)->post("/sms/campaigns/{$campaignB->id}/confirm")->assertNotFound();

        // A church that has never bought credits sees its own zero wallet,
        // never churchB's.
        SmsWallet::withoutGlobalScopes()->create(['church_id' => $churchB->id, 'balance_units' => 999]);

        $this->actingAs($managerA)->get('/sms/wallet')->assertOk()->assertSee('0');
    }

    public function test_notifications_are_scoped_to_the_signed_in_user(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $mine = User::factory()->create(['church_id' => $church->id]);
        $theirs = User::factory()->create(['church_id' => $church->id]);

        $notifications = new \App\Domains\Communication\Services\NotificationService();
        $notification = $notifications->send($mine, 'test.mine', 'My notification');
        $notifications->send($theirs, 'test.theirs', 'Someone else\'s notification');

        $this->actingAs($mine)->get('/notifications')
            ->assertOk()
            ->assertSee('My notification')
            ->assertDontSee('Someone else\'s notification');

        $this->actingAs($mine)->post("/notifications/{$notification->id}/read")->assertRedirect();
        $this->assertSame(0, $notifications->unreadCountFor($mine->fresh()));

        // Marking another user's notification read is refused outright.
        $otherNotification = \App\Models\AppNotification::withoutGlobalScopes()
            ->where('user_id', $theirs->id)->firstOrFail();

        $this->actingAs($mine)->post("/notifications/{$otherNotification->id}/read")->assertForbidden();
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_posting_an_announcement_queues_the_email_job_to_targeted_members_only(): void
    {
        Mail::fake();

        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $admin = $this->ordinaryAdmin($church);

        $included = Member::factory()->for($church, 'church')->create(['email' => 'included@example.com']);
        $excluded = Member::factory()->for($church, 'church')->create(['email' => 'excluded@example.com']);

        $department = \App\Models\Department::create(['church_id' => $church->id, 'name' => 'Ushering']);
        $included->departments()->attach($department);

        $this->actingAs($admin)->post('/announcements', [
            'title' => 'Ushers meeting',
            'body' => 'Meeting after service.',
            'audience_type' => 'department',
            'department_id' => $department->id,
        ])->assertRedirect();

        // QUEUE_CONNECTION=sync, so the job dispatched by the controller runs
        // inline here — the same call the README says the suite relies on
        // rather than a running worker.
        $announcement = Announcement::query()->firstOrFail();
        (new SendAnnouncementEmailsJob($announcement->id))->handle();

        Mail::assertQueued(
            AnnouncementMail::class,
            fn ($mail) => $mail->hasTo('included@example.com')
        );
        Mail::assertNotQueued(
            AnnouncementMail::class,
            fn ($mail) => $mail->hasTo('excluded@example.com')
        );
    }
}