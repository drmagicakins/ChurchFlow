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
        $wallet = $this->wallets->walletFor($request->user()->church);

        return view('sms.campaigns.index', compact('campaigns', 'wallet'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SmsCampaign::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:1600'], // ~10 segments, a sane upper bound
            'audience_type' => ['required', 'in:church,branch,department,group'],
            'organizational_unit_id' => ['required_if:audience_type,branch', 'nullable', 'exists:organizational_units,id'],
            'department_id' => ['required_if:audience_type,department', 'nullable', 'exists:departments,id'],
            'group_id' => ['required_if:audience_type,group', 'nullable', 'exists:groups,id'],
        ]);

        $data['created_by'] = $request->user()->id;
        $campaign = SmsCampaign::create($data);

        return redirect()->route('sms.campaigns.show', $campaign);
    }

    /** §21/§22: the estimate/preview screen — no wallet change happens here. */
    public function show(SmsCampaign $campaign): View
    {
        $this->authorize('view', $campaign);

        return view('sms.campaigns.show', [
            'campaign' => $campaign,
            'estimate' => $campaign->status === 'draft' ? $this->campaigns->estimate($campaign) : null,
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
