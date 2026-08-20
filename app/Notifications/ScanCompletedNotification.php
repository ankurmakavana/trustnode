<?php

namespace App\Notifications;

use App\Enums\Notification\NotificationType;
use App\Models\Scan;
use Illuminate\Notifications\Messages\MailMessage;

class ScanCompletedNotification extends TrustNodeNotification
{
    public function __construct(
        public Scan $scan,
        public int $findingsCount
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $repoName = $this->scan->repository ? $this->scan->repository->name : ($this->scan->target ?? 'Unknown Target');

        return (new MailMessage)
            ->subject("Scan Completed: {$this->scan->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A security scan has completed for {$repoName}.")
            ->line("Scan Name: {$this->scan->name}")
            ->line("Findings Discovered: {$this->findingsCount}")
            ->action('View Scan Details', url('/scans/' . $this->scan->id))
            ->line('Thank you for using TrustNode.');
    }

    public function toArray(object $notifiable): array
    {
        $repoName = $this->scan->repository ? $this->scan->repository->name : ($this->scan->target ?? 'Unknown Target');

        return [
            'type' => NotificationType::SCAN_COMPLETED->value,
            'data' => [
                'scan_id' => $this->scan->id,
                'scan_name' => $this->scan->name,
                'repository_name' => $repoName,
                'findings_count' => $this->findingsCount,
                'message' => "Scan '{$this->scan->name}' completed on {$repoName} with {$this->findingsCount} findings.",
            ]
        ];
    }
}
