<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\Scan;
use Illuminate\Notifications\Messages\MailMessage;

class ScanFailedNotification extends TrustNodeNotification
{
    public function __construct(
        public Scan $scan,
        public string $reason
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $repoName = $this->scan->repository ? $this->scan->repository->name : ($this->scan->target ?? 'Unknown Target');

        return (new MailMessage)
            ->error()
            ->subject("Scan Failed: {$this->scan->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A security scan has failed for {$repoName}.")
            ->line("Scan Name: {$this->scan->name}")
            ->line("Reason: {$this->reason}")
            ->action('View Scan Details', url('/scans/' . $this->scan->id))
            ->line('Please check the logs or try running the scan again.');
    }

    public function toArray(object $notifiable): array
    {
        $repoName = $this->scan->repository ? $this->scan->repository->name : ($this->scan->target ?? 'Unknown Target');

        return [
            'type' => NotificationType::SCAN_FAILED->value,
            'data' => [
                'scan_id' => $this->scan->id,
                'scan_name' => $this->scan->name,
                'repository_name' => $repoName,
                'reason' => $this->reason,
                'message' => "Scan '{$this->scan->name}' failed on {$repoName}. Reason: {$this->reason}",
            ]
        ];
    }
}
