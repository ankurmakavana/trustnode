<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Enums\Notification\NotificationType;
use App\Models\Finding;
use Illuminate\Notifications\Messages\MailMessage;

class HighSeverityFindingNotification extends TrustNodeNotification
{
    public function __construct(
        public Finding $finding
    ) {}

    private function severityValue(): string
    {
        $severity = $this->finding->severity;

        return $severity instanceof \BackedEnum ? (string) $severity->value : (string) $severity;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("High Severity Finding Detected: {$this->finding->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A new {$this->severityValue()} severity finding was detected by the TrustNode Agent.")
            ->line("Title: {$this->finding->title}")
            ->line("Category: {$this->finding->category}")
            ->action('View Finding Details', url('/findings/' . $this->finding->id))
            ->line('Thank you for using TrustNode.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::HIGH_SEVERITY_FINDING->value,
            'data' => [
                'finding_id' => $this->finding->id,
                'title' => $this->finding->title,
                'severity' => $this->severityValue(),
                'message' => "A new {$this->severityValue()} finding '{$this->finding->title}' was detected.",
            ]
        ];
    }
}
