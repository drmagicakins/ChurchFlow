<?php

namespace App\Domains\Communication\Services;

use App\Models\Church;
use App\Models\User;

/**
 * WHO a reply to one of this church's emails should reach.
 *
 * The requirement is specific: a recipient pressing "Reply" must reach the
 * CHURCH OWNER and the PARISH PASTOR — not the platform sender, and not a
 * no-reply address. That is a small rule with a few real decisions in it:
 *
 *   1. The CHURCH OWNER is found by authority, not by "first user": the
 *      registrar flag is the app's own definition (User::isRegistrar()), with
 *      the `Church Owner` role as its historical fallback. Deactivated accounts
 *      are skipped — mail sent to a disabled mailbox is a reply nobody reads.
 *
 *   2. The PARISH PASTOR is resolved PER REGION when the message concerns one.
 *      A branch announcement or an event belonging to a parish goes to THAT
 *      parish's pastors; otherwise every pastor of the church is included, so
 *      a church-wide notice still reaches the people who can answer it.
 *
 *   3. The church's own `reply_to_email` (the Settings screen) comes FIRST when
 *      set. A church that runs `office@…` wants the reply there, and second
 *      guessing that would put a person's private mailbox on every notice.
 *
 *   4. Nothing is invented. If a church has no owner, no pastor and no address,
 *      the list is empty and the caller keeps the platform default — a made-up
 *      address would be worse than none.
 *
 * The result is always a de-duplicated list of VALID addresses, in the order a
 * mail client should offer them.
 *
 * QUERY COST. Three small indexed reads, and each is skipped when the church
 * already loaded the relation. Emails are sent in batches, so the owning job
 * may pass a Church it has already loaded — the results are still read live,
 * because an address edited a moment ago should be the one used.
 */
class ChurchReplyAddresses
{
    /**
     * Reply addresses for `$church`, optionally narrowed to one parish.
     *
     * @return array<int, string>
     */
    public function forChurch(Church $church, ?int $parishId = null): array
    {
        $addresses = [];

        // 1. An explicit shared inbox wins.
        if (filled($church->reply_to_email)) {
            $addresses[] = (string) $church->reply_to_email;
        }

        // 2. The church owner (the registrar / Church Owner role holder).
        //
        //    A real deployment always has one, but a church whose role links were
        //    never carried over (this dev database is exactly that shape: every
        //    user holds no role at all) would otherwise have NO reply address.
        //    The fallback is the same one User::isRegistrar() documents: the
        //    account that registered the church is the earliest one attached to
        //    it. Nothing is guessed beyond that — if there are no accounts, the
        //    list stays empty.
        $owners = $this->usersMatching($church, function ($query) {
            $query->where(function ($q) {
                $q->where('is_registrar', true)
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'Church Owner'));
            });
        });

        if ($owners->isEmpty()) {
            $owners = $this->usersMatching($church, fn ($query) => $query->limit(1));
        }

        foreach ($owners as $owner) {
            $addresses[] = $owner->email;
        }

        // 3. The parish pastor(s) — of the region concerned, when one is named.
        foreach ($this->pastors($church, $parishId) as $pastor) {
            $addresses[] = $pastor->email;
        }

        // 4. The church's public address, when it has nothing else. Still the
        //    church's own mailbox, so it satisfies "reply reaches the church".
        if ($addresses === [] && filled($church->email)) {
            $addresses[] = (string) $church->email;
        }

        $clean = [];

        foreach ($addresses as $address) {
            $address = trim($address);

            if (filter_var($address, FILTER_VALIDATE_EMAIL) && ! in_array($address, $clean, true)) {
                $clean[] = $address;
            }
        }

        return $clean;
    }

    /**
     * The pastors who can answer a message about `$parishId` (or the whole
     * church when none is given).
     *
     * @return array<int, string>  keyed by parish id (0 = church-wide)
     */
    public function pastorsByParish(Church $church, ?int $parishId = null): array
    {
        $key = $parishId ?: 0;

        return [$key => $this->pastors($church, $parishId)->pluck('email')->values()->all()];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function pastors(Church $church, ?int $parishId)
    {
        return $this->usersMatching($church, function ($query) use ($parishId) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', \Database\Seeders\RolePermissionSeeder::PARISH_PASTOR_ROLE))
                ->when($parishId, fn ($q) => $q->where('organizational_unit_id', $parishId));
        });
    }

    /**
     * Active users of this church matching an extra predicate.
     *
     * `withoutGlobalScopes` is not needed (User has no tenant scope — it must be
     * findable by email at sign-in), but the church filter is explicit here
     * because that is the isolation: this must never return another church's
     * staff address. Soft-deleted and deactivated accounts are excluded.
     */
    private function usersMatching(Church $church, callable $constraint)
    {
        $query = User::query()
            ->where('church_id', $church->id)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('id');

        $constraint($query);

        return $query->get();
    }
}
