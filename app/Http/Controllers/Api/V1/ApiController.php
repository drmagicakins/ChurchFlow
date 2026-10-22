<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Subscriptions\Services\PlanFeatureService;
use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\Event;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * §46/§14: the versioned, read-only public API.
 *
 * Why read-only: the first integration every church asks for is "get our data
 * out" — a website showing upcoming events, a giving dashboard, a regional
 * office pulling attendance. Writes are a much bigger correctness surface
 * (validation, audit, approval rules) and are deliberately not exposed until
 * a real integration needs them. A read-only launch is honest about what the
 * key can do and means a leaked key cannot corrupt data.
 *
 * The tenant is bound by AuthenticateApiToken from the token itself, so every
 * query here is automatically scoped to the key's church — there is no
 * church_id parameter to get wrong, and passing one has no effect.
 */
class ApiController extends Controller
{
    public function __construct(private readonly PlanFeatureService $features) {}

    /**
     * §14: `api_access` is a Denomination-and-above feature. Checked here (not
     * only in the UI) so a Starter/Growth key — if one somehow existed — is
     * refused at the API itself rather than silently serving data the plan
     * does not include. Returns 402 Payment Required, which is the honest
     * status for "your plan does not cover this".
     */
    private function assertApiAccess(Request $request): void
    {
        $church = $request->attributes->get('api_church');

        abort_unless(
            $this->features->hasFeature($church, 'api_access'),
            402,
            'API access is not included in this church\'s plan.',
        );
    }

    /** GET /api/v1/me — the token's church and its capabilities. */
    public function me(Request $request): JsonResponse
    {
        $this->assertApiAccess($request);

        $church = $request->attributes->get('api_church');
        $token = $request->attributes->get('api_token');

        return response()->json([
            'data' => [
                'church' => [
                    'id' => $church->id,
                    'name' => $church->name,
                    'timezone' => $church->timezone,
                    'currency' => $church->currency,
                ],
                'token' => [
                    'name' => $token->name,
                    'abilities' => preg_split('/\s+/', (string) $token->abilities, -1, PREG_SPLIT_NO_EMPTY) ?: [],
                    'expires_at' => $token->expires_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /** GET /api/v1/members */
    public function members(Request $request): JsonResponse
    {
        $this->assertApiAccess($request);

        $query = Member::query()->orderBy('full_name');

        if ($request->filled('status')) {
            $query->where('membership_status', $request->string('status')->toString());
        }

        if ($request->filled('branch')) {
            $query->where('organizational_unit_id', (int) $request->input('branch'));
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        return $this->paginated($query->paginate($perPage), fn (Member $m) => [
            'id' => $m->id,
            'membership_number' => $m->membership_number,
            'full_name' => $m->full_name,
            'email' => $m->email,
            'phone' => $m->phone,
            'status' => $m->membership_status,
            'branch_id' => $m->organizational_unit_id,
            'joined_at' => $m->date_joined?->toDateString(),
        ]);
    }

    /** GET /api/v1/events */
    public function events(Request $request): JsonResponse
    {
        $this->assertApiAccess($request);

        $query = Event::query()->orderBy('starts_at');

        // Default to upcoming events; ?from=/?to= override for a historical pull.
        if ($request->filled('from')) {
            $query->where('starts_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('starts_at', '<=', $request->date('to'));
        }
        if (! $request->filled('from') && ! $request->filled('to')) {
            $query->where('starts_at', '>=', now());
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        return $this->paginated($query->paginate($perPage), fn (Event $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'description' => $e->description,
            'venue' => $e->venue,
            'starts_at' => $e->starts_at?->toIso8601String(),
            'ends_at' => $e->ends_at?->toIso8601String(),
            'capacity' => $e->capacity,
            'registered_count' => $e->confirmedCount(),
            'status' => $e->status,
        ]);
    }

    /** GET /api/v1/attendance/sessions */
    public function attendanceSessions(Request $request): JsonResponse
    {
        $this->assertApiAccess($request);

        $query = AttendanceSession::query()->orderByDesc('session_date');

        if ($request->filled('from')) {
            $query->where('session_date', '>=', $request->date('from')?->toDateString());
        }
        if ($request->filled('to')) {
            $query->where('session_date', '<=', $request->date('to')?->toDateString());
        }

        $perPage = min(max((int) $request->input('per_page', 50), 1), 200);

        return $this->paginated($query->paginate($perPage), fn (AttendanceSession $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'type' => $s->type,
            'session_date' => $s->session_date?->toDateString(),
            'present_count' => $s->presentCount(),
            'recorded_count' => $s->records()->count(),
        ]);
    }

    /**
     * A consistent envelope for every list endpoint: data + a small meta block,
     * so a client writes one pagination handler and one parser, not one per
     * endpoint.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator  $paginator
     */
    private function paginated($paginator, callable $map): JsonResponse
    {
        return response()->json([
            'data' => collect($paginator->items())->map($map)->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
