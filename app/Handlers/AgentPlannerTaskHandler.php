<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use Illuminate\Support\Facades\Log;

class AgentPlannerTaskHandler implements AgentTaskHandlerInterface
{
    protected \App\Contracts\AgentQueueInterface $queue;

    public function __construct(\App\Contracts\AgentQueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function supports(string $type): bool
    {
        return $type === 'agent.plan';
    }

    public function handle(array $task): void
    {
        $payload = $task['payload'] ?? [];
        $objective = $payload['objective'] ?? null;
        $step = $payload['step'] ?? 1;
        $lastAction = $payload['last_action'] ?? null;
        $lastResult = $payload['last_result'] ?? null;
        $agentId = $task['agent_id'] ?? 'unknown';

        if (!$objective) {
            Log::warning("AgentPlannerTaskHandler: No objective provided.");
            return;
        }

        if ($objective === 'Audit the current TrustNode codebase') {
            if ($step === 1) {
                // Step 1: Resolve Target and Scan
                $this->queue->enqueue($agentId, 'agent.capability.execute', [
                    'capability_id' => 'agent:scan_codebase',
                    'arguments' => ['target_path' => base_path()],
                    'plan_task_id' => $task['id'],
                    'objective' => $objective,
                    'step' => $step
                ]);
            } elseif ($step === 2 && $lastAction === 'agent:scan_codebase') {
                // Step 2: Verify Result and Complete
                Log::info("Objective Complete: Audit the current TrustNode codebase.", ['result' => $lastResult]);
            }
        } else {
            Log::warning("AgentPlannerTaskHandler: Unknown objective.", ['objective' => $objective]);
        }
    }
}
