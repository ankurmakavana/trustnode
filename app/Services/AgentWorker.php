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

    public function __construct(
        AgentService $agentService,
        AgentQueueInterface $queue,
        AgentTaskHandlerRegistryInterface $registry
    ) {
        $this->agentService = $agentService;
        $this->queue = $queue;
        $this->registry = $registry;
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
            return false;
        }

        try {
            $type = $task['type'] ?? '';
            
            $handler = $this->registry->resolve($type);
            
            $handler->handle($task);
            
            $this->queue->acknowledge($task['id']);
            
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
