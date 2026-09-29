<?php

namespace App\Services;

use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Services\AgentService;
use Throwable;
use Illuminate\Support\Facades\Log;

class AgentWorker
{
    protected AgentService $agentService;
    protected AgentQueueInterface $queue;
    protected AgentTaskHandlerRegistryInterface $registry;
    protected \App\Contracts\AgentSecurityBoundaryInterface $securityBoundary;

    public function __construct(
        AgentService $agentService,
        AgentQueueInterface $queue,
        AgentTaskHandlerRegistryInterface $registry,
        \App\Contracts\AgentSecurityBoundaryInterface $securityBoundary
    ) {
        $this->agentService = $agentService;
        $this->queue = $queue;
        $this->registry = $registry;
        $this->securityBoundary = $securityBoundary;
    }

    /**
     * Run a single worker iteration.
     * Dequeues a task, resolves the handler, executes, and acks/fails.
     *
     * @return bool True if a task was processed, false if queue is empty.
     */
    public function runOnce(): bool
    {
        $agentId = $this->agentService->getAgentId();
        
        $task = $this->queue->dequeue($agentId);
        
        if (!$task) {
            // Reset execution time limit when idle to prevent cumulative timeouts
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }
            return false;
        }

        try {
            $timeout = config('agent.guardrails.max_execution_time', 30);
            if ($timeout > 0 && function_exists('set_time_limit')) {
                // Apply execution timeout guardrail. 
                // Limitation: If triggered, this will cause a fatal error and kill the process, 
                // leaving the task in 'processing' state.
                set_time_limit($timeout);
            }

            $type = $task['type'] ?? '';
            $payload = $task['payload'] ?? [];
            
            $handler = $this->registry->resolve($type);
            
            // SECURITY BOUNDARY
            $operation = $type;
            if ($type === 'agent.capability.execute') {
                $operation = $payload['capability_id'] ?? $type;
            }
            $this->securityBoundary->authorize($agentId, $operation, $payload);
            
            $handler->handle($task);
            
            $this->queue->acknowledge($task['id']);
            
        } catch (\App\Exceptions\AgentSecurityException $e) {
            if (str_contains($e->getMessage(), 'Approval pending')) {
                Log::info("AgentWorker: Task blocked pending approval.", ['task_id' => $task['id']]);
                $this->agentService->setState(\App\Services\AgentService::S_WAITING_APPROVAL);
                // In a robust implementation, the task should be paused.
                // For this MVP, we let it fail in the queue, but it can be requeued later.
                $this->queue->fail($task['id'], $e);
            } else {
                Log::error("AgentWorker: Task failed security authorization.", ['task_id' => $task['id'], 'error' => $e->getMessage()]);
                $this->queue->fail($task['id'], $e);
            }
        } catch (\InvalidArgumentException $e) {
            // Task type not found in registry (Invalid type)
            // TASK 14.1 Limitation: contract requires terminal failure, but AgentQueue::fail() 
            // currently only supports retryable fail.
            // Since we can't redesign AgentQueue::fail() in this task, 
            // we fail() it to respect the existing API.
            Log::error("AgentWorker: Unknown task type rejected.", ['task_id' => $task['id'], 'error' => $e->getMessage()]);
            $this->queue->fail($task['id'], $e);
        } catch (Throwable $e) {
            // Runtime or Infrastructure error
            Log::error("AgentWorker: Task execution failed.", ['task_id' => $task['id'], 'error' => $e->getMessage()]);
            $this->queue->fail($task['id'], $e);
        }

        return true;
    }
}
