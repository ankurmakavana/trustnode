<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\User;

abstract class TrustNodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels based on user preferences.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable instanceof User) {
            if ($notifiable->getPreference('in_app', true)) {
                $channels[] = 'database';
            }
            
            if ($notifiable->getPreference('email', true)) {
                $channels[] = 'mail';
            }
        }

        return $channels;
    }
}
