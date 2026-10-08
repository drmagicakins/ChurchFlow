<?php

namespace App\Domains\PastoralCare\Services;

use App\Models\Member;
use App\Models\PastoralCase;
use App\Models\User;

class PastoralCareService
{
    public function openCase(Member $member, string $type, User $openedBy, ?string $description = null, ?User $assignTo = null): PastoralCase
    {
        $case = PastoralCase::create([
            'church_id' => $member->church_id,
            'member_id' => $member->id,
            'type' => $type,
            'description' => $description,
            'opened_by' => $openedBy->id,
            'assigned_to' => $assignTo?->id ?? $openedBy->id,
        ]);

        $case->notes()->create([
            'author_id' => $openedBy->id,
            'note' => 'Case opened.'.($description ? " {$description}" : ''),
        ]);

        return $case;
    }

    public function addNote(PastoralCase $case, User $author, string $note): PastoralCase
    {
        $case->notes()->create(['author_id' => $author->id, 'note' => $note]);

        return $case;
    }

    public function reassign(PastoralCase $case, User $to): PastoralCase
    {
        $case->update(['assigned_to' => $to->id]);

        return $case;
    }
}
