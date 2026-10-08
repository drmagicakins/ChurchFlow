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
        $items = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(25);

        return view('notifications.index', ['items' => $items]);
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $this->notifications->markRead($notification);

        return back();
    }
}
