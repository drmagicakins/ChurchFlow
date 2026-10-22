<?php

namespace App\Http\Controllers\PlatformAdmin;

use App\Domains\Support\Services\SupportTicketService;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reaches every church's tickets — this works the same way Phase 8's
 * billing overview does: plain Eloquent queries that see across tenants
 * because PlatformAdminOnly set 'tenant.disabled', not because anything
 * here calls withoutGlobalScopes() itself.
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with('church')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            // Urgent first, then high, normal, low. A CASE expression rather
            // than MySQL's field(): this app runs on SQLite in development and
            // tests, and field() simply does not exist there — which turned
            // this query into a 500 the moment it was first exercised on a
            // non-MySQL connection. CASE is portable across SQLite/MySQL/Postgres.
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 when 'low' then 3 else 4 end")
            ->latest()
            ->paginate(25);

        return view('platform-admin.support.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket): View
    {
        return view('platform-admin.support.show', [
            'ticket' => $ticket,
            'replies' => $ticket->replies, // platform staff see internal notes too — unlike the church-side view
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal_note' => ['boolean'],
        ]);

        $this->tickets->reply($ticket, $request->user(), $data['body'], $request->boolean('is_internal_note'));

        return back()->with('status', 'Reply posted.');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['required', 'exists:users,id']]);

        $this->tickets->assign($ticket, User::findOrFail($data['assigned_to']));

        return back()->with('status', 'Ticket assigned.');
    }

    public function resolve(SupportTicket $ticket): RedirectResponse
    {
        $this->tickets->resolve($ticket);

        return back()->with('status', 'Ticket marked resolved.');
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $this->tickets->close($ticket);

        return back()->with('status', 'Ticket closed.');
    }
}
