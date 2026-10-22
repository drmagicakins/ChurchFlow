<?php

namespace App\Domains\Support\Services;

use App\Domains\Files\Services\FileUploadService;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class SupportTicketService
{
    public function __construct(private readonly FileUploadService $uploads) {}

    /** §54: issue, category, description, screenshot, priority — exactly the fields the brief names. */
    public function create(User $creator, array $data, ?UploadedFile $screenshot = null): SupportTicket
    {
        $ticket = SupportTicket::create([
            'church_id' => $creator->church_id,
            'created_by' => $creator->id,
            'subject' => $data['subject'],
            'category' => $data['category'],
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'normal',
            // Set explicitly rather than relying on the column default: a DB
            // default fills the ROW, but the in-memory model returned by
            // create() would still report `status` as null until re-fetched,
            // which then silently breaks any caller (or test) that reads the
            // status off the object it just created.
            'status' => $data['status'] ?? 'open',
        ]);

        if ($screenshot) {
            $uploaded = $this->uploads->storeImage($screenshot, $creator->church, $creator, $ticket);
            $ticket->update(['screenshot_upload_id' => $uploaded->id]);
        }

        return $ticket;
    }

    public function reply(SupportTicket $ticket, User $author, string $body, bool $isInternalNote = false): SupportTicketReply
    {
        // An internal note only ever makes sense from platform staff — a
        // church user has no "internal" to post to.
        abort_if($isInternalNote && !$author->is_platform_admin, 403, 'Only platform staff can post an internal note.');

        $reply = $ticket->replies()->create([
            'author_id' => $author->id,
            'body' => $body,
            'is_internal_note' => $isInternalNote,
        ]);

        // A customer-visible reply from support implicitly moves a ticket
        // out of "waiting on us" into "waiting on them" — but never
        // silently closes it; only an explicit resolve()/close() does that.
        if ($author->is_platform_admin && !$isInternalNote && $ticket->status === 'open') {
            $ticket->update(['status' => 'waiting_on_customer']);
        } elseif (!$author->is_platform_admin && $ticket->status === 'waiting_on_customer') {
            $ticket->update(['status' => 'in_progress']);
        }

        return $reply;
    }

    public function assign(SupportTicket $ticket, User $assignee): SupportTicket
    {
        abort_unless($assignee->is_platform_admin, 422, 'Tickets can only be assigned to platform staff.');

        $ticket->update(['assigned_to' => $assignee->id, 'status' => 'in_progress']);

        return $ticket;
    }

    public function resolve(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        return $ticket;
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => 'closed']);

        return $ticket;
    }

    public function reopen(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => 'open', 'resolved_at' => null]);

        return $ticket;
    }
}
