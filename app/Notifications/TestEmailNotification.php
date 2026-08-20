<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use App\Models\User;

class TestEmailNotification extends TrustNodeNotification
{
    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('TrustNode - Test Email')
                    ->line('This is a test email sent from TrustNode settings.')
                    ->line('If you are receiving this, your SMTP configuration is correct!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'system_alert',
            'message' => 'Test email sent successfully.',
        ];
    }
}
