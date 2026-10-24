<?php

// --- Phase 1 additions: merge these into routes/web.php, do not overwrite ---

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', fn () => view('auth.register'))->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// --- Phase 10/13: the platform status page (§44) ---
// Public and unauthenticated on purpose — a monitoring tool can't log in, and
// neither can a church whose only symptom is "the app seems broken". A status
// page nobody can reach during an outage is useless.
//
// `/health` is the human-readable page; `/health.json` is the machine
// endpoint a load balancer or uptime monitor was already pointed at in Phase
// 10, kept in the same shape so nothing already configured breaks. Both are
// rate-limited: they run real probes against the database, cache, queue and
// storage, so an unthrottled public endpoint is a way to hammer those
// backends from outside.
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('health');

Route::get('/health.json', [\App\Http\Controllers\HealthController::class, 'json'])
    ->middleware('throttle:60,1')
    ->name('health.json');

// --- Phase 10: the public landing page (§27-39) ---
// This is the marketing landing page built for Phase 10 — a real route
// backed by LandingController, NOT the default `laravel new` welcome view.
// It replaces the earlier marketing-suite home page at "/"; anything else
// that used to answer "/" now redirects here so there is exactly one page
// owning that URI (two routes on one URI is a silent bug). The rest of the
// marketing site (features, pricing, about, legal, resources) is still
// registered from routes/marketing.php at the bottom of this file.
Route::get('/', [\App\Http\Controllers\LandingController::class, 'index'])->name('landing');

// --- Phase 8: public pricing (§36) + pre-tenant checkout (§1-8) ---
Route::get('/plans', [\App\Http\Controllers\PlanController::class, 'index'])->name('plans.index');

// 'auth' ONLY, deliberately no IdentifyTenant: the person using these has
// no church yet — payment comes first. Every query in CheckoutController
// filters by user_id explicitly instead.
Route::middleware('auth')->group(function () {
    Route::get('/checkout/plans/{plan}', [\App\Http\Controllers\CheckoutController::class, 'review'])->name('checkout.review');
    Route::post('/checkout/plans/{plan}', [\App\Http\Controllers\CheckoutController::class, 'start'])->name('checkout.start');
    Route::get('/checkout/{checkout}/verify', [\App\Http\Controllers\CheckoutController::class, 'verify'])->name('checkout.verify');
    Route::get('/checkout/{checkout}/failed', [\App\Http\Controllers\CheckoutController::class, 'failed'])->name('checkout.failed');
    Route::post('/checkout/{checkout}/retry', [\App\Http\Controllers\CheckoutController::class, 'retry'])->name('checkout.retry');

    // Logout lives here, beside the checkout routes, and NOT inside the
    // IdentifyTenant group below. Ending a session must never depend on tenant
    // resolution: IdentifyTenant redirects a church-less user to /plans before
    // the controller runs, which meant a mid-signup (unpaid) user could not
    // sign out at all — the session was never invalidated.
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// --- Phase 3: public event registration (§13) ---
// Outside the auth/tenant group ON PURPOSE. The brief asks for "a form where
// visitors can register for events without logging in", and a visitor has no
// session and no church. The event is resolved by its public slug and the
// PublicEventRegistrationController filters every follow-up query by that
// event's church_id, so this is not an opening onto another tenant's data.
//
// Throttled: it is an unauthenticated POST that writes rows.
Route::get('/e/{slug}', [\App\Http\Controllers\PublicEventRegistrationController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('events.public.register');

Route::post('/e/{slug}', [\App\Http\Controllers\PublicEventRegistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('events.public.store');

// EnsureSubscriptionAllowsAccess is Phase 8's gate: an active subscription is
// required to reach the application proper. The billing routes live INSIDE this
// group and are exempted by name in the middleware itself, so a lapsed church
// can always reach the page that lets it pay and get back in.
Route::middleware(['auth', \App\Http\Middleware\IdentifyTenant::class, \App\Http\Middleware\EnsureSubscriptionAllowsAccess::class])->group(function () {
    // The dashboard view renders figures supplied by DashboardController (member
    // counts, pending approvals, account balances, recent subventions). The route
    // used to call view('dashboard') directly, which skips the controller and
    // hands the template none of its variables — every panel that guarded on
    // them then failed on an undefined variable.
    Route::get('/dashboard', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    // Global search: live suggestions (JSON) and the full results page. Throttled
    // because it fires as the user types.
    Route::get('/search', [\App\Http\Controllers\SearchController::class, 'index'])->name('search.index');
    Route::get('/search/suggest', [\App\Http\Controllers\SearchController::class, 'suggest'])
        ->middleware('throttle:60,1')->name('search.suggest');

    // --- Phase 2: People ---
    Route::get('/members/export', [\App\Http\Controllers\MemberImportExportController::class, 'export'])->name('members.export');
    // The blank sheet a church fills in for a bulk import. Declared here, next to
    // the import route, so `members/{member}` below can never swallow it.
    Route::get('/members/sample-sheet', [\App\Http\Controllers\MemberImportExportController::class, 'sample'])->name('members.sample');
    Route::post('/members/import', [\App\Http\Controllers\MemberImportExportController::class, 'import'])->name('members.import');
    Route::post('/members/bulk-status', [\App\Http\Controllers\MemberController::class, 'bulkUpdateStatus'])->name('members.bulk-status');
    // Phase 10: member photo upload (§23/§43) — uploads via FileUploadService,
    // served back only through a signed, time-limited download URL.
    Route::post('/members/{member}/photo', [\App\Http\Controllers\MemberPhotoController::class, 'store'])->name('members.photo');
    Route::resource('members', \App\Http\Controllers\MemberController::class)->except(['create', 'edit']);
    Route::resource('families', \App\Http\Controllers\FamilyController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/families/{family}/members', [\App\Http\Controllers\FamilyController::class, 'attachMember'])->name('families.members.attach');
    Route::delete('/families/{family}/members/{member}', [\App\Http\Controllers\FamilyController::class, 'detachMember'])->name('families.members.detach');

    Route::resource('departments', \App\Http\Controllers\DepartmentController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/departments/{department}/members', [\App\Http\Controllers\DepartmentController::class, 'attachMember'])->name('departments.members.attach');
    Route::delete('/departments/{department}/members/{member}', [\App\Http\Controllers\DepartmentController::class, 'detachMember'])->name('departments.members.detach');

    Route::resource('groups', \App\Http\Controllers\GroupController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/groups/{group}/members', [\App\Http\Controllers\GroupController::class, 'attachMember'])->name('groups.members.attach');
    Route::delete('/groups/{group}/members/{member}', [\App\Http\Controllers\GroupController::class, 'detachMember'])->name('groups.members.detach');

    // --- §4/§14: Branches (organisational units) ---
    // The model and its plan limit existed from the start, but nothing let a
    // church owner add one — so max_branches was unenforceable and every branch
    // picker elsewhere was permanently empty.
    Route::get('/branches', [\App\Http\Controllers\OrganizationalUnitController::class, 'index'])->name('branches.index');
    Route::post('/branches', [\App\Http\Controllers\OrganizationalUnitController::class, 'store'])->name('branches.store');
    Route::delete('/branches/{branch}', [\App\Http\Controllers\OrganizationalUnitController::class, 'destroy'])->name('branches.destroy');

    // --- Phase 3: Activities ---
    Route::resource('events', \App\Http\Controllers\EventController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('/events/{event}/register', [\App\Http\Controllers\EventController::class, 'register'])->name('events.register');
    Route::post('/events/{event}/registrations/remove', [\App\Http\Controllers\EventController::class, 'cancelRegistration'])->name('events.registrations.cancel');

    // §13/§16: the visitor list on an event, its public link, and reminders.
    // `share-form` mints the public slug on first use, so an event created
    // before this feature existed gets a link the moment someone asks for one.
    Route::post('/events/{event}/public-form', [\App\Http\Controllers\EventController::class, 'sharePublicForm'])->name('events.public-form');
    Route::post('/events/{event}/visitors/{visitor}/remove', [\App\Http\Controllers\EventController::class, 'cancelVisitor'])->name('events.visitors.cancel');
    Route::post('/events/{event}/remind/email', [\App\Http\Controllers\EventController::class, 'emailReminder'])->name('events.remind.email');
    Route::post('/events/{event}/remind/sms', [\App\Http\Controllers\EventController::class, 'smsReminder'])->name('events.remind.sms');

    Route::resource('attendance', \App\Http\Controllers\AttendanceController::class)
        ->parameters(['attendance' => 'session'])
        ->only(['index', 'store', 'show']);
    Route::post('/attendance/{session}/records', [\App\Http\Controllers\AttendanceController::class, 'recordBulk'])->name('attendance.records');

    Route::resource('announcements', \App\Http\Controllers\AnnouncementController::class)->only(['index', 'store', 'show', 'destroy']);

    Route::resource('tasks', \App\Http\Controllers\TaskController::class)->only(['index', 'store', 'update', 'destroy']);
    // §24: reminder popup. `due` returns the reminders the signed-in user should
    // see now (JSON, polled by the layout); `dismiss` stamps one as shown so it
    // does not re-appear. Declared before the resource-less routes so the paths
    // stay unambiguous.
    Route::get('/tasks/reminders/due', [\App\Http\Controllers\TaskController::class, 'dueReminders'])->name('tasks.reminders.due');
    Route::post('/tasks/{task}/reminder/dismiss', [\App\Http\Controllers\TaskController::class, 'dismissReminder'])->name('tasks.reminders.dismiss');

    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');

    // --- §30: Advanced reports ---
    // Listing, a per-report page and a CSV of exactly what the page shows.
    // The {report} parameter is a report KEY (attendance_summary, giving_summary,
    // ...) — see ReportsService::catalogue() — not a row id.
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    // The export route is declared BEFORE the bare {report} route so the more
    // specific path wins regardless of framework ordering: /reports/x/export
    // must never be parsed as report "x/export".
    Route::get('/reports/{report}/export', [\App\Http\Controllers\ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{report}', [\App\Http\Controllers\ReportController::class, 'show'])->name('reports.show');

    // --- Phase 10: secure file downloads (§23, §43) ---
    // `signed` proves the link wasn't tampered with and hasn't expired;
    // FileDownloadController re-checks tenant ownership on top of that.
    Route::get('/files/{uploadedFile}', [\App\Http\Controllers\FileDownloadController::class, 'show'])
        ->middleware('signed')
        ->name('files.show');

    // --- Phase 4: Finance ---
    Route::resource('finance/accounts', \App\Http\Controllers\FinancialAccountController::class)
        ->parameters(['accounts' => 'account'])
        ->only(['index', 'store', 'show'])
        ->names('finance.accounts');

    Route::post('/finance/accounts/{account}/income', [\App\Http\Controllers\TransactionController::class, 'storeIncome'])->name('finance.transactions.income');    Route::post('/finance/accounts/{account}/expense', [\App\Http\Controllers\TransactionController::class, 'storeExpense'])->name('finance.transactions.expense');
    Route::post('/finance/transfer', [\App\Http\Controllers\TransactionController::class, 'storeTransfer'])->name('finance.transactions.transfer');
    Route::post('/finance/transactions/{transaction}/void', [\App\Http\Controllers\TransactionController::class, 'void'])->name('finance.transactions.void');

    // --- Settlement bank accounts (pastor's account, readable by the registrar) ---
    // The pastor records where a subvention/remittance should be paid; the
    // super-admin/registrar reads the same list to make the transfer. The
    // policy scopes a pastor to their own row and an admin to all of them.
    Route::get('/finance/bank-accounts', [\App\Http\Controllers\BankAccountController::class, 'index'])->name('finance.bank-accounts.index');
    Route::post('/finance/bank-accounts', [\App\Http\Controllers\BankAccountController::class, 'store'])->name('finance.bank-accounts.store');
    Route::post('/finance/bank-accounts/{bankAccount}/verify', [\App\Http\Controllers\BankAccountController::class, 'verify'])->name('finance.bank-accounts.verify');
    Route::delete('/finance/bank-accounts/{bankAccount}', [\App\Http\Controllers\BankAccountController::class, 'destroy'])->name('finance.bank-accounts.destroy');

    Route::get('/approvals', [\App\Http\Controllers\ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [\App\Http\Controllers\ApprovalController::class, 'reject'])->name('approvals.reject');

    Route::resource('finance/budgets', \App\Http\Controllers\BudgetController::class)
        ->parameters(['budgets' => 'budget'])
        ->only(['index', 'store', 'show'])
        ->names('finance.budgets');
    Route::post('/finance/budgets/{budget}/items', [\App\Http\Controllers\BudgetController::class, 'storeItem'])->name('finance.budgets.items.store');
    Route::delete('/finance/budgets/{budget}/items/{item}', [\App\Http\Controllers\BudgetController::class, 'destroyItem'])->name('finance.budgets.items.destroy');

    Route::resource('finance/loans', \App\Http\Controllers\LoanController::class)
        ->parameters(['loans' => 'loan'])
        ->only(['index', 'store', 'show'])
        ->names('finance.loans');
    Route::post('/finance/loans/{loan}/payments', [\App\Http\Controllers\LoanController::class, 'recordPayment'])->name('finance.loans.payments');

    // --- Phase 5: Subvention ---
    // 'update' is included so a rule set can be edited after creation — the
    // policy already allowed it (SubventionRuleSetPolicy::update) but there was
    // no route and no form, so once a set was created its rules were frozen.
    Route::resource('subvention/rule-sets', \App\Http\Controllers\SubventionRuleSetController::class)
        ->parameters(['rule-sets' => 'ruleSet'])
        ->only(['index', 'store', 'show', 'update'])
        ->names('subvention.rule-sets');

    Route::resource('subvention/periods', \App\Http\Controllers\SubventionPeriodController::class)
        ->only(['index', 'store'])
        ->names('subvention.periods');

    Route::resource('subvention/submissions', \App\Http\Controllers\SubventionSubmissionController::class)
        ->parameters(['submissions' => 'submission'])
        ->only(['index', 'store', 'show', 'update'])
        ->names('subvention.submissions');

    Route::prefix('subvention/submissions/{submission}')->name('subvention.submissions.')->group(function () {
        Route::post('/submit', [\App\Http\Controllers\SubventionSubmissionController::class, 'submit'])->name('submit');
        Route::post('/review', [\App\Http\Controllers\SubventionSubmissionController::class, 'review'])->name('review');
        Route::post('/return', [\App\Http\Controllers\SubventionSubmissionController::class, 'return'])->name('return');
        Route::post('/reopen', [\App\Http\Controllers\SubventionSubmissionController::class, 'reopen'])->name('reopen');
        Route::post('/approve', [\App\Http\Controllers\SubventionSubmissionController::class, 'approve'])->name('approve');
        Route::post('/reject', [\App\Http\Controllers\SubventionSubmissionController::class, 'reject'])->name('reject');
    });

    // --- Phase 6: Pastoral Care ---
    Route::resource('pastoral/prayer-requests', \App\Http\Controllers\PrayerRequestController::class)
        ->parameters(['prayer-requests' => 'prayerRequest'])
        ->only(['index', 'store', 'update'])
        ->names('pastoral.prayer-requests');

    Route::resource('pastoral/cases', \App\Http\Controllers\PastoralCaseController::class)
        ->parameters(['cases' => 'case'])
        ->only(['index', 'store', 'show'])
        ->names('pastoral.cases');
    Route::post('/pastoral/cases/{case}/notes', [\App\Http\Controllers\PastoralCaseController::class, 'addNote'])->name('pastoral.cases.notes');
    Route::post('/pastoral/cases/{case}/close', [\App\Http\Controllers\PastoralCaseController::class, 'close'])->name('pastoral.cases.close');

    Route::resource('pastoral/appointments', \App\Http\Controllers\AppointmentController::class)
        ->parameters(['appointments' => 'appointment'])
        ->only(['index', 'store', 'update'])
        ->names('pastoral.appointments');

    // --- Church settings (§14, §37) ---
    // The church's own profile, team and credentials, and role/permission
    // matrix. Inside the tenant group so IdentifyTenant binds the church these
    // settings belong to; the controller re-checks settings.manage on every
    // action rather than trusting the route alone.
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/profile', [\App\Http\Controllers\SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('/settings/team', [\App\Http\Controllers\SettingsController::class, 'storeTeamMember'])->name('settings.team.store');
    Route::post('/settings/team/{member}/role', [\App\Http\Controllers\SettingsController::class, 'updateTeamMemberRole'])->name('settings.team.role');
    Route::post('/settings/team/{member}/parish', [\App\Http\Controllers\SettingsController::class, 'updateTeamMemberParish'])->name('settings.team.parish');
    // §14: the owner ticks which capabilities a parish pastor has. Owner-only in
    // the controller; the route exists for everybody so the 403 is explicit
    // rather than a missing-route 404 that looks like a broken link.
    Route::post('/settings/team/{member}/permissions', [\App\Http\Controllers\SettingsController::class, 'updateTeamMemberPermissions'])->name('settings.team.permissions');
    Route::post('/settings/team/{member}/password', [\App\Http\Controllers\SettingsController::class, 'resetTeamMemberPassword'])->name('settings.team.password');
    Route::post('/settings/team/{member}/toggle', [\App\Http\Controllers\SettingsController::class, 'toggleTeamMember'])->name('settings.team.toggle');
    Route::delete('/settings/team/{member}', [\App\Http\Controllers\SettingsController::class, 'destroyTeamMember'])->name('settings.team.destroy');

    // --- Phase 7: Communication ---
    Route::get('/notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationCenterController::class, 'markRead'])->name('notifications.read');

    Route::resource('sms/campaigns', \App\Http\Controllers\SmsCampaignController::class)
        ->parameters(['campaigns' => 'campaign'])
        ->only(['index', 'store', 'show'])
        ->names('sms.campaigns');
    Route::post('/sms/campaigns/{campaign}/confirm', [\App\Http\Controllers\SmsCampaignController::class, 'confirm'])->name('sms.campaigns.confirm');

    Route::get('/sms/wallet', [\App\Http\Controllers\SmsWalletController::class, 'show'])->name('sms.wallet.show');
    Route::post('/sms/wallet/purchase', [\App\Http\Controllers\SmsWalletController::class, 'purchase'])->name('sms.wallet.purchase');
    Route::post('/sms/wallet/credit', [\App\Http\Controllers\SmsWalletController::class, 'credit'])->name('sms.wallet.credit');

    // --- Phase 12: Support tickets (§54), church side ---
    // A church raises an issue (with an optional screenshot) and follows it up;
    // platform staff answer from the admin area. Kept in the tenant group so
    // every ticket is scoped to the raising church by the global tenant scope.
    //
    // The URI is /help, NOT /support: the public marketing site already owns
    // `GET /support` (routes/marketing.php, name `support`), and two routes on
    // one URI is a silent bug — whichever registers last wins, so the church's
    // own ticket list would have been unreachable behind the marketing page.
    // The route NAME stays `support.*`, which is what every internal link uses.
    Route::resource('help', \App\Http\Controllers\SupportTicketController::class)
        ->parameters(['help' => 'ticket'])
        ->only(['index', 'store', 'show'])
        ->names('support');
    Route::post('/help/{ticket}/reply', [\App\Http\Controllers\SupportTicketController::class, 'reply'])->name('support.reply');

    // --- Phase 8: Billing center (billing.* names are exempt from the
    // subscription-status restriction by EnsureSubscriptionAllowsAccess) ---
    Route::get('/billing', [\App\Http\Controllers\BillingController::class, 'show'])->name('billing.show');
    Route::get('/billing/invoices', [\App\Http\Controllers\BillingController::class, 'invoices'])->name('billing.invoices');
    // Phase 11: the plan management screens — subscribe out of a trial, and
    // the single upgrade/downgrade table for a paid subscription.
    Route::get('/billing/plans', [\App\Http\Controllers\BillingController::class, 'plans'])->name('billing.plans');
    Route::post('/billing/subscribe', [\App\Http\Controllers\BillingController::class, 'subscribeNow'])->name('billing.subscribe');
    Route::post('/billing/cancel', [\App\Http\Controllers\BillingController::class, 'requestCancellation'])->name('billing.cancel');
    Route::post('/billing/reactivate', [\App\Http\Controllers\BillingController::class, 'reactivate'])->name('billing.reactivate');
        Route::get('/billing/change-plan/preview', [\App\Http\Controllers\BillingController::class, 'previewPlanChange'])->name('billing.change-plan.preview');
        Route::post('/billing/change-plan', [\App\Http\Controllers\BillingController::class, 'changePlan'])->name('billing.change-plan');
        Route::post('/billing/sms-credits', [\App\Http\Controllers\BillingController::class, 'buySmsCredits'])->name('billing.sms-credits');

        // --- Phase 9: Onboarding, tours, analytics (\u00a711, \u00a729-32, \u00a755) ---
        Route::get('/setup', [\App\Http\Controllers\SetupWizardController::class, 'show'])->name('setup.show');
        Route::post('/setup/complete', [\App\Http\Controllers\SetupWizardController::class, 'complete'])->name('setup.complete');
        Route::post('/setup/skip', [\App\Http\Controllers\SetupWizardController::class, 'skip'])->name('setup.skip');

        Route::prefix('tours/{slug}')->name('tours.')->group(function () {
            Route::get('/should-show', [\App\Http\Controllers\TourController::class, 'shouldShow'])->name('should-show');
            Route::post('/start', [\App\Http\Controllers\TourController::class, 'start'])->name('start');
            Route::post('/next', [\App\Http\Controllers\TourController::class, 'next'])->name('next');
            Route::post('/previous', [\App\Http\Controllers\TourController::class, 'previous'])->name('previous');
            Route::post('/finish', [\App\Http\Controllers\TourController::class, 'finish'])->name('finish');
            Route::post('/skip', [\App\Http\Controllers\TourController::class, 'skip'])->name('skip');
            Route::post('/dismiss', [\App\Http\Controllers\TourController::class, 'dismissPermanently'])->name('dismiss');
            Route::post('/restart', [\App\Http\Controllers\TourController::class, 'restart'])->name('restart');
        });

        Route::post('/analytics/track', [\App\Http\Controllers\AnalyticsController::class, 'track'])->name('analytics.track');
    });
    Route::middleware(['auth', \App\Http\Middleware\PlatformAdminOnly::class])
        ->prefix('platform-admin')
        ->name('platform-admin.')
        ->group(function () {
            Route::get('/', \App\Http\Controllers\PlatformAdmin\DashboardController::class)->name('dashboard');

            Route::get('/billing', [\App\Http\Controllers\PlatformAdmin\BillingOverviewController::class, 'index'])->name('billing.index');
            Route::get('/billing/churches/{church}', [\App\Http\Controllers\PlatformAdmin\BillingOverviewController::class, 'showChurch'])->name('billing.church');
            Route::post('/billing/churches/{church}/suspend', [\App\Http\Controllers\PlatformAdmin\BillingOverviewController::class, 'suspend'])->name('billing.suspend');
            Route::post('/billing/churches/{church}/reactivate', [\App\Http\Controllers\PlatformAdmin\BillingOverviewController::class, 'reactivate'])->name('billing.reactivate');

            Route::get('/settings', [\App\Http\Controllers\PlatformAdmin\PlatformSettingsController::class, 'edit'])->name('settings.edit');
            Route::post('/settings', [\App\Http\Controllers\PlatformAdmin\PlatformSettingsController::class, 'update'])->name('settings.update');

            Route::get('/feature-flags', [\App\Http\Controllers\PlatformAdmin\FeatureFlagController::class, 'index'])->name('feature-flags.index');
            Route::post('/feature-flags', [\App\Http\Controllers\PlatformAdmin\FeatureFlagController::class, 'store'])->name('feature-flags.store');
            Route::post('/feature-flags/{featureFlag}/toggle', [\App\Http\Controllers\PlatformAdmin\FeatureFlagController::class, 'toggleGlobal'])->name('feature-flags.toggle');
            Route::post('/feature-flags/{featureFlag}/override', [\App\Http\Controllers\PlatformAdmin\FeatureFlagController::class, 'setChurchOverride'])->name('feature-flags.override');

            // --- Phase 12: Support tickets (§54), platform-admin side ---
            // PlatformAdminOnly sets 'tenant.disabled', so these queries see
            // every church's tickets without any withoutGlobalScopes() call.
            Route::get('/support', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'index'])->name('support.index');
            Route::get('/support/{ticket}', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'show'])->name('support.show');
            Route::post('/support/{ticket}/reply', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'reply'])->name('support.reply');
            Route::post('/support/{ticket}/assign', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'assign'])->name('support.assign');
            Route::post('/support/{ticket}/resolve', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'resolve'])->name('support.resolve');
            Route::post('/support/{ticket}/close', [\App\Http\Controllers\PlatformAdmin\SupportTicketController::class, 'close'])->name('support.close');
        });

// --- Marketing / public site ---
// routes/marketing.php owns the landing page and every public marketing route.
// It has to be required here or none of them are registered: the file existed and
// was fully written, but nothing ever loaded it, so GET / fell through to a 404
// and every named route the marketing navbar and footer link to was missing.
// Included last, and without the auth/IdentifyTenant group above, on purpose —
// these pages are public and have no tenant context.
require __DIR__.'/marketing.php';
