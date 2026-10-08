<?php

namespace App\Domains\Communication\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    public function send(User $user, string $type, string $title, ?string $body = null, ?string $actionUrl = null): AppNotification
    {
        abort_if(!$user->church_id, 500, 'Cannot send an in-app notification to a user with no church context.');

        return AppNotification::create([
            'church_id' => $user->church_id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);
    }

    public function markRead(AppNotification $notification): void
    {
        $notification->markRead();
    }

    public function unreadCountFor(User $user): int
    {
        return AppNotification::query()->where('user_id', $user->id)->unread()->count();
    }
}
