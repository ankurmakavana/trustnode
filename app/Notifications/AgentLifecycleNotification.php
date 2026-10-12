<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Enums\Notification\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class AgentLifecycleNotification extends TrustNodeNotification
{
    public function __construct(
        public string $agentId,
        public string $event, // 'started', 'stopped', 'failed'
        public ?string $message = null
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $actionText = ucwords(str_replace('_', ' ', $this->event));
        return (new MailMessage)
            ->subject("Agent {$actionText}: {$this->agentId}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The TrustNode Agent '{$this->agentId}' has reported: {$actionText}.")
            ->line($this->message ?? '')
            ->line('Thank you for using TrustNode.');
    }

    public function toArray(object $notifiable): array
    {
        $type = match($this->event) {
            'started' => NotificationType::AGENT_STARTED->value,
            'stopped' => NotificationType::AGENT_STOPPED->value,
            'failed' => NotificationType::AGENT_FAILED->value,
            'permission_requested' => NotificationType::PERMISSION_REQUESTED->value,
            default => NotificationType::SYSTEM_ALERT->value,
        };

        return [
            'type' => $type,
            'data' => [
                'agent_id' => $this->agentId,
                'event' => $this->event,
                'message' => $this->message ?? "Agent {$this->agentId} {$this->event}.",
            ]
        ];
    }
}
