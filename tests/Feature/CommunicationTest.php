<?php

namespace Tests\Feature;

use App\Domains\Communication\Listeners\NotifySubmitterOfSubventionDecision;
use App\Domains\Communication\Services\NotificationService;
use App\Domains\Subventions\Events\SubventionSubmissionDecided;
use App\Mail\SubventionDecisionMail;
use App\Models\Church;
use App\Models\OrganizationalUnit;
use App\Models\SubventionPeriod;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_sends_marks_read_and_counts_unread(): void
    {
        $church = Church::factory()->create();
        // The in-app notification center is tenant-scoped, so a tenant must be
        // bound for NotificationService to be usable at all (as it is inside a
        // real request via IdentifyTenant).
        app()->instance('tenant.church_id', $church->id);
        $user = User::factory()->create(['church_id' => $church->id]);
        $service = new NotificationService();

        $n1 = $service->send($user, 'test.one', 'First');
        $service->send($user, 'test.two', 'Second');

        $this->assertSame(2, $service->unreadCountFor($user));

        $service->markRead($n1);
        $this->assertSame(1, $service->unreadCountFor($user));
    }

    public function test_a_church_cannot_see_another_churchs_notifications(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = User::factory()->create(['church_id' => $churchA->id]);
        $userB = User::factory()->create(['church_id' => $churchB->id]);

        $service = new NotificationService();
        $service->send($userA, 'x', 'A note');
        $service->send($userB, 'x', 'B note');

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, \App\Models\AppNotification::all());
    }

    public function test_subvention_decision_notifies_the_submitter_in_app_and_by_email(): void
    {
        Mail::fake();

        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $submitter = User::factory()->create(['church_id' => $church->id]);

        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Test Branch']);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $period = SubventionPeriod::factory()->for($church, 'church')->create();

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [],
            'submitted_by' => $submitter->id,
        ]);

        $event = new SubventionSubmissionDecided($submission, 'approved');
        (new NotifySubmitterOfSubventionDecision(new NotificationService()))->handle($event);

        $notification = \App\Models\AppNotification::where('user_id', $submitter->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('subvention.approved', $notification->type);

        Mail::assertQueued(SubventionDecisionMail::class, fn ($mail) => $mail->hasTo($submitter->email));
    }

    public function test_no_email_or_notification_crash_when_submission_has_no_submitter(): void
    {
        Mail::fake();

        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $unitType = UnitType::create(['church_id' => $church->id, 'name' => 'Province', 'level' => 0]);
        $branch = OrganizationalUnit::create(['church_id' => $church->id, 'unit_type_id' => $unitType->id, 'name' => 'Test Branch']);
        $ruleSet = SubventionRuleSet::factory()->for($church, 'church')->create();
        $period = SubventionPeriod::factory()->for($church, 'church')->create();

        $submission = SubventionSubmission::create([
            'church_id' => $church->id,
            'subvention_period_id' => $period->id,
            'organizational_unit_id' => $branch->id,
            'subvention_rule_set_id' => $ruleSet->id,
            'figures' => [],
        ]); // no submitted_by

        $event = new SubventionSubmissionDecided($submission, 'approved');
        (new NotifySubmitterOfSubventionDecision(new NotificationService()))->handle($event);

        Mail::assertNothingQueued();
        $this->assertCount(0, \App\Models\AppNotification::all());
    }
}
