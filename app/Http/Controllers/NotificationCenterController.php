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
