<?php

namespace App\Domains\Search\Services;

use App\Models\Announcement;
use App\Models\Department;
use App\Models\Event;
use App\Models\Family;
use App\Models\FinancialAccount;
use App\Models\Group;
use App\Models\Member;
use App\Models\User;
use Closure;

/**
 * Cross-module search for the topbar.
 *
 * Two guarantees, both structural rather than remembered per query:
 *
 *  - Tenant isolation: every searched model uses BelongsToTenant, so its global
 *    scope confines each query to the signed-in user's church. This service never
 *    passes a church id and so can't get one wrong.
 *  - Permissions: each source declares the permission that lets a user see that
 *    kind of record at all (mirroring the sidebar and policies). A source the user
 *    can't see is never queried, so it can't leak even a result count.
 *
 * Adding a searchable module means adding one entry to sources().
 */
class GlobalSearchService
{
    public const MIN_LENGTH = 2;

    /**
     * @return array<int, array{key:string,label:string,icon:string,items:array<int,array{title:string,subtitle:string,url:string}>}>
     */
    public function search(User $user, string $term, int $perGroup = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $like = '%'.$this->escapeLike($term).'%';
        $groups = [];

        foreach ($this->sources() as $source) {
            if ($source['permission'] !== null && ! $user->hasPermission($source['permission'])) {
                continue;
            }

            $items = ($source['query'])($like, $perGroup)
                ->map(fn ($row) => ($source['present'])($row))
                ->values()
                ->all();

            if ($items !== []) {
                $groups[] = ['key' => $source['key'], 'label' => $source['label'], 'icon' => $source['icon'], 'items' => $items];
            }
        }

        return $groups;
    }

    /** @return array<int, array<string, mixed>> */
    private function sources(): array
    {
        return [
            [
                'key' => 'members', 'label' => 'Members', 'icon' => 'users', 'permission' => 'members.view',
                'query' => fn (string $like, int $n) => Member::query()
                    ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)->orWhere('membership_number', 'like', $like))
                    ->orderBy('full_name')->limit($n)->get(),
                'present' => fn (Member $m) => [
                    'title' => $m->full_name,
                    'subtitle' => collect([$m->membership_number, $m->email ?: $m->phone])->filter()->implode(' · '),
                    'url' => route('members.show', $m),
                ],
            ],
            [
                'key' => 'families', 'label' => 'Families', 'icon' => 'family', 'permission' => 'members.view',
                'query' => fn (string $like, int $n) => Family::query()->where('name', 'like', $like)->orderBy('name')->limit($n)->get(),
                'present' => fn (Family $f) => ['title' => $f->name, 'subtitle' => 'Family', 'url' => route('families.show', $f)],
            ],
            [
                'key' => 'departments', 'label' => 'Departments', 'icon' => 'building', 'permission' => 'members.view',
                'query' => fn (string $like, int $n) => Department::query()->where('name', 'like', $like)->orderBy('name')->limit($n)->get(),
                'present' => fn (Department $d) => ['title' => $d->name, 'subtitle' => 'Department', 'url' => route('departments.show', $d)],
            ],
            [
                'key' => 'groups', 'label' => 'Groups', 'icon' => 'branches', 'permission' => 'members.view',
                'query' => fn (string $like, int $n) => Group::query()->where('name', 'like', $like)->orderBy('name')->limit($n)->get(),
                'present' => fn (Group $g) => ['title' => $g->name, 'subtitle' => 'Group', 'url' => route('groups.show', $g)],
            ],
            [
                // The Events page is open to every signed-in user (no permission in the sidebar), so search matches.
                'key' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'permission' => null,
                'query' => fn (string $like, int $n) => Event::query()
                    ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('venue', 'like', $like))
                    ->orderByDesc('starts_at')->limit($n)->get(),
                'present' => fn (Event $e) => [
                    'title' => $e->title,
                    'subtitle' => collect([$e->starts_at?->format('M j, Y'), $e->venue])->filter()->implode(' · '),
                    'url' => route('events.show', $e),
                ],
            ],
            [
                'key' => 'finance', 'label' => 'Finance accounts', 'icon' => 'wallet', 'permission' => 'finance.view',
                'query' => fn (string $like, int $n) => FinancialAccount::query()->where('name', 'like', $like)->orderBy('name')->limit($n)->get(),
                'present' => fn (FinancialAccount $a) => ['title' => $a->name, 'subtitle' => ucfirst($a->type).' account', 'url' => route('finance.accounts.show', $a)],
            ],
            [
                'key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'permission' => 'announcements.manage',
                'query' => fn (string $like, int $n) => Announcement::query()
                    ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('body', 'like', $like))
                    ->latest()->limit($n)->get(),
                'present' => fn (Announcement $a) => [
                    'title' => $a->title, 'subtitle' => $a->created_at?->format('M j, Y') ?? '', 'url' => route('announcements.show', $a),
                ],
            ],
        ];
    }

    /** Stops a user-typed % or _ acting as a wildcard (and `\` as an escape). */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
