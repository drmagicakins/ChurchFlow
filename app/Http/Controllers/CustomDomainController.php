<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Services\PlanFeatureService;
use App\Models\Church;
use App\Models\CustomDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * §14: the church's custom domain.
 *
 * `custom_domain` is part of the Denomination plan (and above). The feature
 * flag existed, and the pricing page advertised it, but there was no screen
 * and no record of the hostname — so a church paying for it had no way to set
 * one up. This controller is that flow:
 *
 *   claim a hostname → publish the TXT record we show → verify → live.
 *
 * Like API access, this is the church owner's alone (a hostname is a
 * platform-level routing decision, not a parish setting) and the feature is
 * re-checked on every write, so a downgrade is enforced immediately.
 */
class CustomDomainController extends Controller
{
    public function __construct(private readonly PlanFeatureService $features) {}

    public function index(Request $request): View
    {
        $church = $this->authorizeAccess($request);

        return view('custom-domain.index', [
            'church' => $church,
            'enabled' => $this->features->hasFeature($church, 'custom_domain'),
            'requiredPlan' => $this->features->requiredPlanName('custom_domain'),
            'domain' => CustomDomain::query()->latest('id')->first(),
            // What the CNAME should point at, so the instruction is complete:
            // a TXT record alone proves ownership but does not route traffic.
            'target' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'churchflow.app',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $church = $this->authorizeAccess($request, requireFeature: true);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255'],
        ]);

        $hostname = CustomDomain::normalise($data['hostname']);

        // Validated AFTER normalising, so "https://Grace.org/" is judged as
        // "grace.org" rather than rejected for characters the person did not
        // mean to type. A bare hostname with at least one dot, labels of
        // letters/digits/hyphens — deliberately strict, because this string
        // becomes a routing rule.
        validator(
            ['hostname' => $hostname],
            ['hostname' => [
                'required',
                'regex:/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/',
                Rule::unique('custom_domains', 'hostname'),
            ]],
            [
                'hostname.regex' => 'Enter a bare domain such as gracechapel.org — no https:// and no path.',
                'hostname.unique' => 'That domain is already claimed by another workspace.',
            ],
        )->validate();

        // One domain per church: a second claim replaces the pending one but
        // keeps the same row, so a church cannot accumulate hostnames.
        $domain = CustomDomain::query()->latest('id')->first() ?? new CustomDomain(['church_id' => $church->id]);

        $domain->forceFill([
            'church_id' => $church->id,
            'hostname' => $hostname,
            'status' => 'pending',
            'verification_token' => CustomDomain::newVerificationToken(),
            'verified_at' => null,
            'last_checked_at' => null,
            'last_error' => null,
        ])->save();

        return redirect()->route('custom-domain.index')->with('status', "Domain {$hostname} claimed. Publish the DNS record below to verify it.");
    }

    /**
     * Check the domain's DNS TXT records for our verification token.
     *
     * Deliberately isolated in one method: this is the only place that talks
     * to DNS, so swapping the lookup (or stubbing it in a test) does not touch
     * any of the state transitions around it.
     */
    public function verify(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request, requireFeature: true);

        $domain = CustomDomain::query()->latest('id')->first();
        abort_if($domain === null, 404, 'Claim a domain first.');

        $records = $this->txtRecords($domain->verificationRecordName());
        $found = in_array($domain->verification_token, $records, true);

        $domain->forceFill([
            'last_checked_at' => now(),
            'status' => $found ? 'verified' : 'pending',
            'verified_at' => $found ? ($domain->verified_at ?? now()) : null,
            'last_error' => $found
                ? null
                : ($records === [] ? 'No TXT record found at '.$domain->verificationRecordName().'.' : 'A TXT record exists but does not contain the expected token.'),
        ])->save();

        return back()->with(
            $found ? 'status' : 'error',
            $found
                ? "Domain {$domain->hostname} verified. Point its CNAME at ".$this->targetHost().' to serve the workspace.'
                : 'Not verified yet. '.$domain->last_error.' DNS can take time to propagate — try again shortly.',
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $domain = CustomDomain::query()->latest('id')->first();
        abort_if($domain === null, 404);

        $hostname = $domain->hostname;
        $domain->delete();

        return back()->with('status', "Domain {$hostname} released.");
    }

    /** @return array<int, string> */
    private function txtRecords(string $name): array
    {
        $records = @dns_get_record($name, DNS_TXT) ?: [];

        return array_values(array_filter(array_map(
            fn ($r) => trim((string) ($r['txt'] ?? '')),
            $records,
        )));
    }

    private function targetHost(): string
    {
        return (string) (parse_url(config('app.url'), PHP_URL_HOST) ?: 'churchflow.app');
    }

    private function authorizeAccess(Request $request, bool $requireFeature = false): Church
    {
        $user = $request->user();
        $church = $user->church;

        abort_unless($user->isChurchOwner(), 403, 'Only the church owner can manage the custom domain.');
        abort_unless($church !== null, 403);

        if ($requireFeature) {
            abort_unless(
                $this->features->hasFeature($church, 'custom_domain'),
                403,
                'A custom domain is not included in your plan.',
            );
        }

        return $church;
    }
}
