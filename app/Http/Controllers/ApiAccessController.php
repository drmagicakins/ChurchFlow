<?php

namespace App\Http\Controllers;

use App\Domains\Subscriptions\Services\PlanFeatureService;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * §46/§14: the church's own API keys.
 *
 * `api_access` was a promise on the pricing page with no way to use it: the
 * ApiToken model and the public routes exist, but a church had no screen to
 * issue a key, so the feature was unreachable. This is that screen.
 *
 * Two things are checked here, and they are deliberately separate:
 *
 *  1. The user must be the church owner. An API key is a credential that
 *     reads the whole church's data, so it sits with the owner rather than
 *     with "anyone with settings.manage" — the same reasoning that keeps
 *     account deletion with the registrar.
 *  2. The church's plan must include `api_access`. Checked on every action,
 *     not just on the GET, so a downgrade takes effect immediately rather
 *     than leaving an existing key (and this screen) usable.
 *
 * Tenant isolation comes from BelongsToTenant on ApiToken plus the
 * 'tenant.church_id' IdentifyTenant binds, so a token row can never be listed
 * or revoked from another church.
 */
class ApiAccessController extends Controller
{
    public function __construct(private readonly PlanFeatureService $features) {}

    public function index(Request $request): View
    {
        $church = $this->authorizeAccess($request);

        return view('api-access.index', [
            'church' => $church,
            'enabled' => $this->features->hasFeature($church, 'api_access'),
            'requiredPlan' => $this->features->requiredPlanName('api_access'),
            'tokens' => ApiToken::query()->with('creator')->latest('id')->get(),
            // Shown once, immediately after issuing. A flash rather than a
            // column, because the plaintext is unrecoverable by design — see
            // ApiToken's docblock.
            'plaintext' => session('api_token_plaintext'),
            'endpoints' => [
                ['GET', '/api/v1/me', 'The key\'s church, its abilities and expiry.'],
                ['GET', '/api/v1/members', 'Members, filterable by status and branch.'],
                ['GET', '/api/v1/events', 'Events, upcoming by default, or a from/to range.'],
                ['GET', '/api/v1/attendance/sessions', 'Attendance sessions with present counts.'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $church = $this->authorizeAccess($request, requireFeature: true);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        // Read-only at launch: every issued key can read, none can write. That
        // is the API controller's own contract, so it is stated here rather
        // than offered as a choice the caller could get wrong.
        [$token, $plaintext] = ApiToken::issue(
            $church,
            $request->user(),
            $data['name'],
            ['read'],
            $data['expires_in_days'] ?? null,
        );

        return redirect()->route('api-access.index')
            ->with('status', "API key \"{$token->name}\" created.")
            ->with('api_token_plaintext', $plaintext);
    }

    public function revoke(Request $request, ApiToken $token): RedirectResponse
    {
        $this->authorizeAccess($request);

        // The token is resolved through the tenant scope, so a key belonging to
        // another church is simply not found — never mutated.
        abort_unless($token->church_id === $request->user()->church_id, 404);

        if ($token->revoked_at === null) {
            // Revoke rather than delete: an integration that suddenly starts
            // failing should be traceable to a key that was deliberately
            // turned off, not to a row that vanished.
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return back()->with('status', "API key \"{$token->name}\" revoked.");
    }

    /**
     * The owner, whose church, and — optionally — whether the plan covers it.
     * Returns the church so callers don't re-read it from the request.
     */
    private function authorizeAccess(Request $request, bool $requireFeature = false): \App\Models\Church
    {
        $user = $request->user();
        $church = $user->church;

        abort_unless($user->isChurchOwner(), 403, 'Only the church owner can manage API keys.');
        abort_unless($church !== null, 403);

        if ($requireFeature) {
            abort_unless(
                $this->features->hasFeature($church, 'api_access'),
                403,
                'API access is not included in your plan.',
            );
        }

        return $church;
    }
}
