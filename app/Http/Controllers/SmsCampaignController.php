<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Jobs\SendSmsCampaignJob;
use App\Domains\Communication\Services\SmsCampaignService;
use App\Domains\Communication\Services\SmsWalletService;
use App\Models\SmsCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsCampaignController extends Controller
{
    public function __construct(
        private readonly SmsCampaignService $campaigns,
        private readonly SmsWalletService $wallets,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SmsCampaign::class);

        $campaigns = SmsCampaign::query()->latest()->paginate(25);

        // §17-19: the balance shown is the one this user can actually spend.
        // A parish pastor sees (and buys for) their OWN parish wallet; the
        // church owner sees the main church's. showing the main church's
        // balance to a pastor was the invitation to spend it.
        $wallet = $this->wallets->walletForUser($request->user());

        // §19/§21 targeting: the compose form needs the actual audiences to
        // offer once "a specific branch / a department / a group" is chosen.
        // Tenant-scoped by the global scope, so never another church's — and for
        // a parish pastor that scope narrows the branch list to their own parish,
        // so the form cannot offer a branch they are not allowed to text.
        // Departments need one more rule than the tenant scope gives them: a
        // department filed against the whole church is stored with a NULL
        // parish and would otherwise vanish for every parish pastor.
        //
        // OrganizationalUnit carries no organizational_unit_id column, so
        // TenantScope does NOT narrow branches: without the whereKey below a
        // parish pastor was offered every OTHER parish as an SMS audience. The
        // server already forces their own parish on save, so this only made the
        // picker misleading — but it leaked the sibling parishes' names.
        $branches = \App\Models\OrganizationalUnit::query()
            ->when($request->user()->isParishScoped(), fn ($q) => $q->whereKey($request->user()->organizational_unit_id))
            ->orderBy('name')
            ->get();
        $departments = \App\Models\Department::query()->visibleTo($request->user())->orderBy('name')->get();
        $groups = \App\Models\Group::query()->orderBy('name')->get();

        return view('sms.campaigns.index', compact('campaigns', 'wallet', 'branches', 'departments', 'groups'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SmsCampaign::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1600'], // ~10 segments, a sane upper bound
            // 'church_wide' — matching the announcements form and the value
            // SmsCampaignService::audienceFor() recognises. See the note in
            // AnnouncementController: the form and the validator disagreed
            // about the spelling, so the whole-church option never validated.
            'audience_type' => ['required', 'in:church_wide,branch,department,group'],
            'organizational_unit_id' => ['required_if:audience_type,branch', 'nullable', 'exists:organizational_units,id'],
            'department_id' => ['required_if:audience_type,department', 'nullable', 'exists:departments,id'],
            'group_id' => ['required_if:audience_type,group', 'nullable', 'exists:groups,id'],
        ]);

        $data['created_by'] = $request->user()->id;

        // §17-19: bill the campaign to the wallet its creator may spend. A
        // parish pastor's campaign draws on the parish wallet, so the main
        // church's credit can never pay for it.
        $data['wallet_unit_id'] = $request->user()->isParishScoped()
            ? (int) $request->user()->organizational_unit_id
            : \App\Models\SmsWallet::CHURCH_WIDE;

        // A parish pastor can only target their own parish — the scope would
        // hide the rest of the church anyway, and targeting "a branch" they do
        // not belong to would produce an empty audience rather than an error.
        if ($request->user()->isParishScoped() && $data['audience_type'] === 'branch') {
            $data['organizational_unit_id'] = $request->user()->organizational_unit_id;
        }

        $campaign = SmsCampaign::create($data);

        return redirect()->route('sms.campaigns.show', $campaign);
    }

    /** §21/§22: the estimate/preview screen — no wallet change happens here. */
    public function show(SmsCampaign $campaign): View
    {
        $this->authorize('view', $campaign);

        // Once a campaign is confirmed, its recipient list is snapshotted on
        // the campaign — the estimate is only meaningful while it is a draft.
        $recipients = $campaign->recipients()
            ->with('member')
            ->latest('id')
            ->paginate(50);

        return view('sms.campaigns.show', [
            'campaign' => $campaign,
            'estimate' => $campaign->status === 'draft' ? $this->campaigns->estimate($campaign) : null,
            'recipients' => $recipients,
        ]);
    }

    public function confirm(Request $request, SmsCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $campaign = $this->campaigns->confirmAndQueue($campaign, $request->user());

        SendSmsCampaignJob::dispatch($campaign->id);

        return redirect()->route('sms.campaigns.show', $campaign)
            ->with('status', "Campaign confirmed — {$campaign->reserved_units} SMS units reserved and queued to send.");
    }
}
