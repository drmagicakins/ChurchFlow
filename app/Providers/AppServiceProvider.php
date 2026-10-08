<?php

namespace App\Providers;

use App\Domains\Communication\Listeners\NotifySubmitterOfSubventionDecision;
use App\Domains\Communication\Providers\NullSmsProvider;
use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Subscriptions\Events\ChurchActivated;
use App\Domains\Subscriptions\Gateways\NullPaymentGateway;
use App\Domains\Subscriptions\Gateways\PaymentGatewayInterface;
use App\Domains\Onboarding\Listeners\TrackOnboardingStarted;
use App\Domains\Subventions\Events\SubventionSubmissionDecided;
use App\Models\Announcement;
use App\Models\Appointment;
use App\Models\Approval;
use App\Models\AttendanceSession;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Event;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Group;
use App\Models\Loan;
use App\Models\Member;
use App\Models\PastoralCase;
use App\Models\PrayerRequest;
use App\Models\SmsCampaign;
use App\Models\SubventionRuleSet;
use App\Models\SubventionSubmission;
use App\Models\Task;
use App\Models\Transaction;
use App\Policies\AnnouncementPolicy;
use App\Policies\AppointmentPolicy;
use App\Policies\ApprovalPolicy;
use App\Policies\AttendanceSessionPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EventPolicy;
use App\Policies\FamilyPolicy;
use App\Policies\FinancialAccountPolicy;
use App\Policies\GroupPolicy;
use App\Policies\LoanPolicy;
use App\Policies\MemberPolicy;
use App\Policies\PastoralCasePolicy;
use App\Policies\PrayerRequestPolicy;
use App\Policies\SmsCampaignPolicy;
use App\Policies\SubventionRuleSetPolicy;
use App\Policies\SubventionSubmissionPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Default binding is the network-free NullSmsProvider (see its own
        // docblock) — swap this binding for TermiiProvider/TwilioProvider,
        // configured from a platform-admin setting (§28), never a
        // hard-coded choice here.
        $this->app->bind(SmsProviderInterface::class, NullSmsProvider::class);

        // Same idea for payments (§5): network-free default; bind
        // PaystackGateway/FlutterwaveGateway here once real credentials
        // exist. Nothing else in the app names a provider.
        $this->app->bind(PaymentGatewayInterface::class, NullPaymentGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(Member::class, MemberPolicy::class);
        Gate::policy(Family::class, FamilyPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(AttendanceSession::class, AttendanceSessionPolicy::class);
        Gate::policy(Announcement::class, AnnouncementPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(FinancialAccount::class, FinancialAccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(Approval::class, ApprovalPolicy::class);
        Gate::policy(SubventionRuleSet::class, SubventionRuleSetPolicy::class);
        Gate::policy(SubventionSubmission::class, SubventionSubmissionPolicy::class);
        Gate::policy(PrayerRequest::class, PrayerRequestPolicy::class);
        Gate::policy(PastoralCase::class, PastoralCasePolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(SmsCampaign::class, SmsCampaignPolicy::class);

        // Stored in approvable_type instead of the raw class name: keeps
        // the approvals table stable across future namespace refactors and
        // keeps stored strings short/readable. Extend this map — never the
        // engine itself — for any future approvable model.
        Relation::morphMap([
            'transaction' => Transaction::class,
            'subvention_submission' => SubventionSubmission::class,
        ]);

        EventFacade::listen(SubventionSubmissionDecided::class, NotifySubmitterOfSubventionDecision::class);
        EventFacade::listen(ChurchActivated::class, TrackOnboardingStarted::class);
    }
}
