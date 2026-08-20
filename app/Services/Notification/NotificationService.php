<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Models\Notification;
use App\Enums\Notification\NotificationType;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification to a specific user.
     */
    public function send(User $user, NotificationType $type, array $data): Notification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'data' => $data,
        ]);
    }

    /**
     * Send a notification to all users in a tenant/organization.
     * In this system, tenant is represented by users who share data or we just send to a specific list.
     */
    public function sendToMultiple(iterable $users, NotificationType $type, array $data): void
    {
        foreach ($users as $user) {
            $this->send($user, $type, $data);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }
}
