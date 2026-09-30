<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use Illuminate\Support\Facades\Log;

class AgentPlannerTaskHandler implements AgentTaskHandlerInterface
{
    protected \App\Contracts\AgentQueueInterface $queue;
    protected \App\Contracts\AgentPlannerInterface $planner;

    public function __construct(
        \App\Contracts\AgentQueueInterface $queue,
        \App\Contracts\AgentPlannerInterface $planner
    ) {
        $this->queue = $queue;
        $this->planner = $planner;
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

        $plan = $this->planner->plan($objective, $step, $lastAction, $lastResult);

        if ($plan) {
            $this->queue->enqueue($agentId, 'agent.capability.execute', [
                'capability_id' => $plan['capability_id'],
                'arguments' => $plan['arguments'],
                'plan_task_id' => $task['id'],
                'objective' => $objective,
                'step' => $step
            ]);
        }
    }
}
