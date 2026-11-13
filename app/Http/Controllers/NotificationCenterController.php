<?php

namespace App\Http\Controllers;

use App\Domains\Communication\Services\NotificationService;
use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $filter = $request->string('filter', 'all')->toString();

        $items = AppNotification::query()
            ->where('user_id', $user->id)
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('notifications.index', [
            'items' => $items,
            'filter' => $filter,
            'unreadCount' => AppNotification::query()->where('user_id', $user->id)->unread()->count(),
            'totalCount' => AppNotification::query()->where('user_id', $user->id)->count(),
        ]);
    }

    /**
     * The header bell's dropdown: the few most recent items, as JSON.
     *
     * Deliberately separate from index() — the full page paginates and carries
     * filters, while the dropdown needs a short list and the count. Read-only,
     * and scoped to the caller, so it leaks nothing that index() would not.
     */
    public function feed(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();

        $items = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (AppNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'url' => $n->action_url,
                'unread' => $n->read_at === null,
                'when' => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread' => AppNotification::query()->where('user_id', $user->id)->unread()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $this->notifications->markRead($notification);

        return back();
    }

    /** Mark every unread notification for this user as read in one action. */
    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
