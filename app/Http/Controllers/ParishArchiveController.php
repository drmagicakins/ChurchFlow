<?php

namespace App\Http\Controllers;

use App\Domains\ParishArchive\Services\ParishArchiveService;
use App\Models\Church;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Downloading a parish's records, and asking how many there are.
 *
 * WHO MAY EXPORT
 * --------------
 * The church owner — the account that registered the church — and nobody
 * else. Not because an export route is dangerous in isolation, but because
 * this archive contains pastoral care notes, confidential prayer requests and
 * the whole finance ledger of the church. "Who may take a copy of everything"
 * is an ownership right, exactly like deleting an account, so it is checked
 * with isRegistrar() rather than with a permission that can be ticked.
 *
 * WHAT IT DOES NOT DO
 * -------------------
 * It does not change anything. Both actions are reads: `download` builds the
 * ZIP in memory and streams it back, `counts` counts rows. There is no delete
 * here and no write of any kind — which is what makes this safe to place next
 * to the delete action on the Settings → Team screen.
 *
 * PARISH SCOPING
 * --------------
 * A parish is resolved ONLY from this church's own organizational units. The
 * unit id in the URL is never trusted, so a tampered id cannot reach another
 * tenant's branch (OrganizationalUnit has a tenant scope, and the church_id is
 * asserted here as well — belt and braces, because this route returns data).
 */
class ParishArchiveController extends Controller
{
    public function __construct(private readonly ParishArchiveService $archive) {}

    /**
     * GET /parish-archive/counts?organizational_unit_id=…
     *
     * What the export would contain, per module, without building it. The
     * owner uses this to answer "is there anything worth keeping in this
     * parish before I remove it?". JSON, because the caller is a screen
     * deciding what to say — not a person reading a page.
     */
    public function counts(Request $request): JsonResponse
    {
        $church = $this->ownChurchOrAbort($request);
        $parish = $this->parishOrAbort($request, $church);

        return response()->json([
            'scope' => $parish?->name ?? 'Whole church',
            'counts' => $this->archive->counts($church, $parish),
            'total' => $this->archive->total($church, $parish),
        ]);
    }

    /**
     * GET /parish-archive/download?organizational_unit_id=…
     *
     * Streams the ZIP. The filename sent to the browser is RFC 5987 encoded,
     * so a parish called "St. Mary's — Main" downloads with its real name on
     * every browser instead of a mangled one.
     */
    public function download(Request $request): Response
    {
        $church = $this->ownChurchOrAbort($request);
        $parish = $this->parishOrAbort($request, $church);

        [$filename, $bytes] = $this->archive->export($church, $parish);

        return response($bytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
            ),
            'Content-Length' => (string) strlen($bytes),
            // An archive of personal data is never cached by shared proxies.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** The signed-in user's church, or a refusal if they have none. */
    private function ownChurchOrAbort(Request $request): Church
    {
        abort_unless($request->user()?->isRegistrar(), 403, 'Only the church owner can export parish records.');

        $church = $request->user()->church;

        abort_if($church === null, 403, 'This account is not attached to a church.');

        return $church;
    }

    /**
     * The requested parish, or null for the whole church.
     *
     * A unit id that exists but belongs to another church is reported as
     * "not found" rather than "forbidden": telling the caller that the id is
     * real somewhere else would leak the existence of another tenant's branch.
     */
    private function parishOrAbort(Request $request, Church $church): ?OrganizationalUnit
    {
        $unitId = $request->query('organizational_unit_id');

        if ($unitId === null || $unitId === '') {
            return null;
        }

        $parish = OrganizationalUnit::query()
            ->where('id', (int) $unitId)
            ->where('church_id', $church->id)
            ->first();

        abort_if($parish === null, 404, 'That parish was not found in this church.');

        return $parish;
    }
}
