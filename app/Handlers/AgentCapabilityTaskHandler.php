<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\AgentCapabilityRegistryInterface;
use App\Contracts\AgentApprovalServiceInterface;
use Illuminate\Support\Facades\Log;

class AgentCapabilityTaskHandler implements AgentTaskHandlerInterface
{
    protected AgentCapabilityRegistryInterface $registry;
    protected \App\Contracts\AgentQueueInterface $queue;
    protected AgentApprovalServiceInterface $approvalService;

    public function __construct(AgentCapabilityRegistryInterface $registry, \App\Contracts\AgentQueueInterface $queue, AgentApprovalServiceInterface $approvalService)
    {
        $this->registry = $registry;
        $this->queue = $queue;
        $this->approvalService = $approvalService;
    }

    public function supports(string $type): bool
    {
        return $type === 'agent.capability.execute';
    }

    public function handle(array $task): void
    {
        $payload = $task['payload'] ?? [];
        $capabilityId = $payload['capability_id'] ?? null;
        $arguments = $payload['arguments'] ?? [];
        $planTaskId = $payload['plan_task_id'] ?? null;
        $agentId = $task['agent_id'] ?? 'unknown';

        $capability = $this->registry->resolve($capabilityId);
        if (!$capability) {
            throw new \RuntimeException("Capability not found: {$capabilityId}");
        }

        $context = ['agent_id' => $agentId, 'task_id' => $task['id'] ?? null];
        $result = $capability->execute($arguments, $context);

        // Result returned to planner
        if ($planTaskId) {
            $this->queue->enqueue($agentId, 'agent.plan', [
                'objective' => $payload['objective'] ?? null,
                'last_action' => $capabilityId,
                'last_result' => $result,
                'step' => ($payload['step'] ?? 1) + 1
            ]);
        }
    }
}
