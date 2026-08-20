<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\Report;
use Illuminate\Notifications\Messages\MailMessage;

class ReportReadyNotification extends TrustNodeNotification
{
    public function __construct(
        public $scanId,
        public $scanName
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Report Ready: {$this->scanName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your security report for scan '{$this->scanName}' is now ready to download.")
            ->action('Download Report', url('/api/scans/' . $this->scanId . '/report/download'))
            ->line('Thank you for using TrustNode.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::REPORT_READY->value,
            'data' => [
                'scan_id' => $this->scanId,
                'scan_name' => $this->scanName,
                'message' => "Your report for scan '{$this->scanName}' is ready.",
            ]
        ];
    }
}
