<?php

namespace App\Http\Controllers;

use App\Domains\Support\Services\SupportTicketService;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(): View
    {
        $this->authorize('viewAny', SupportTicket::class);

        $tickets = SupportTicket::query()->latest()->paginate(25);

        return view('support.index', compact('tickets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SupportTicket::class);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:billing,technical,feature_request,other'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'screenshot' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $ticket = $this->tickets->create($request->user(), $data, $request->file('screenshot'));

        return redirect()->route('support.show', $ticket)->with('status', 'Support ticket submitted.');
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorize('view', $ticket);

        return view('support.show', [
            'ticket' => $ticket,
            'replies' => $ticket->visibleReplies, // a church user never sees internal notes — see SupportTicket::visibleReplies()
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorize('reply', $ticket);

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $this->tickets->reply($ticket, $request->user(), $data['body']);

        return back()->with('status', 'Reply sent.');
    }
}
