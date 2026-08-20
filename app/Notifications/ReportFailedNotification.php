<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class ReportFailedNotification extends TrustNodeNotification
{
    public function __construct(
        public $scanId,
        public $scanName,
        public $reason
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Report Generation Failed: {$this->scanName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your security report for scan '{$this->scanName}' failed to generate.")
            ->line("Reason: {$this->reason}")
            ->line('Please try generating the report again.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::REPORT_FAILED->value,
            'data' => [
                'scan_id' => $this->scanId,
                'scan_name' => $this->scanName,
                'reason' => $this->reason,
                'message' => "Report generation for scan '{$this->scanName}' failed. Reason: {$this->reason}",
            ]
        ];
    }
}
