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
    private function sanitizeError(string $message): string
    {
        $message = preg_replace('/(password|secret|key|token|auth_token|api_key)=[\w\-\.\%\+]+/i', '$1=***', $message);
        $message = preg_replace('/Bearer\s+[\w\-\.\+]+/i', 'Bearer ***', $message);
        $message = \App\Services\SecretRedactor::redact($message) ?? '';
        return substr($message, 0, 800);
    }

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
            if (isset($task['user_id'])) {
                if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\App\Models\User::find($task['user_id'])) {
                    throw new \App\Exceptions\AgentSecurityException("Task execution denied: User no longer exists.");
                }
                \App\Services\TenantContext::setUserId((int)$task['user_id']);
            } else {
                throw new \LogicException("Cannot process task: Task ownership context is missing.");
            }

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

                $throttleKey = "agent_notification:permission:" . $this->agentService->getAgentId();
                if (\Illuminate\Support\Facades\Cache::add($throttleKey, true, now()->addMinutes(60))) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('agent_activity_logs')) {
                        \App\Models\AgentActivityLog::create([
                            'agent_id' => $this->agentService->getAgentId(),
                            'action' => 'permission_requested',
                            'properties' => ['message' => 'Agent requires explicit developer approval.', 'task_id' => $task['id']],
                            'user_id' => $task['user_id'] ?? null,
                            'organization_id' => isset($task['user_id']) ? \App\Models\Team::whereHas('users', fn($q) => $q->whereKey($task['user_id']))->value('organization_id') : null,
                        ]);
                    }
                    \App\Services\Notification\NotificationRouter::dispatch(
                        \App\Enums\Notification\NotificationType::PERMISSION_REQUESTED,
                        new \App\Services\Notification\NotificationContext(),
                        new \App\Notifications\AgentLifecycleNotification($this->agentService->getAgentId(), 'permission_requested', 'A task requires explicit developer approval to execute.')
                    );
                }

                $this->queue->fail($task['id'], $e);
            } else {
                $safeError = $this->sanitizeError($e->getMessage());
                Log::error("AgentWorker: Task failed security authorization.", ['task_id' => $task['id'], 'error' => $safeError]);

                $throttleKey = "agent_notification:failed:" . $this->agentService->getAgentId();
                if (\Illuminate\Support\Facades\Cache::add($throttleKey, true, now()->addMinutes(15))) {
                    if (\Illuminate\Support\Facades\Schema::hasTable('agent_activity_logs')) {
                        \App\Models\AgentActivityLog::create([
                            'agent_id' => $this->agentService->getAgentId(),
                            'action' => 'failed',
                            'properties' => ['message' => 'Security authorization failed.', 'task_id' => $task['id'], 'error' => $safeError],
                            'user_id' => $task['user_id'] ?? null,
                            'organization_id' => isset($task['user_id']) ? \App\Models\Team::whereHas('users', fn($q) => $q->whereKey($task['user_id']))->value('organization_id') : null,
                        ]);
                    }
                    \App\Services\Notification\NotificationRouter::dispatch(
                        \App\Enums\Notification\NotificationType::AGENT_FAILED,
                        new \App\Services\Notification\NotificationContext(),
                        new \App\Notifications\AgentLifecycleNotification($this->agentService->getAgentId(), 'failed', 'Agent failed security authorization: ' . $e->getMessage())
                    );
                }

                $this->queue->fail($task['id'], $e);
            }
        } catch (\InvalidArgumentException $e) {
            $safeError = $this->sanitizeError($e->getMessage());
            Log::error("AgentWorker: Unknown task type rejected.", ['task_id' => $task['id'], 'error' => $safeError]);

            $throttleKey = "agent_notification:failed:" . $this->agentService->getAgentId();
            if (\Illuminate\Support\Facades\Cache::add($throttleKey, true, now()->addMinutes(15))) {
                if (\Illuminate\Support\Facades\Schema::hasTable('agent_activity_logs')) {
                    \App\Models\AgentActivityLog::create([
                        'agent_id' => $this->agentService->getAgentId(),
                        'action' => 'failed',
                        'properties' => ['message' => 'Invalid task type.', 'task_id' => $task['id'], 'error' => $safeError],
                        'user_id' => $task['user_id'] ?? null,
                        'organization_id' => isset($task['user_id']) ? \App\Models\Team::whereHas('users', fn($q) => $q->whereKey($task['user_id']))->value('organization_id') : null,
                    ]);
                }
                \App\Services\Notification\NotificationRouter::dispatch(
                    \App\Enums\Notification\NotificationType::AGENT_FAILED,
                    new \App\Services\Notification\NotificationContext(),
                    new \App\Notifications\AgentLifecycleNotification($this->agentService->getAgentId(), 'failed', 'Agent received an invalid or unsupported task.')
                );
            }
            $this->queue->fail($task['id'], $e);
        } catch (Throwable $e) {
            // Runtime or Infrastructure error
            $safeError = $this->sanitizeError($e->getMessage());
            Log::error("AgentWorker: Task execution failed.", ['task_id' => $task['id'], 'error' => $safeError]);

            $throttleKey = "agent_notification:failed:" . $this->agentService->getAgentId();
            if (\Illuminate\Support\Facades\Cache::add($throttleKey, true, now()->addMinutes(15))) {
                if (\Illuminate\Support\Facades\Schema::hasTable('agent_activity_logs')) {
                    \App\Models\AgentActivityLog::create([
                        'agent_id' => $this->agentService->getAgentId(),
                        'action' => 'failed',
                        'properties' => ['message' => 'Task execution failed.', 'task_id' => $task['id'], 'error' => $safeError],
                        'user_id' => $task['user_id'] ?? null,
                        'organization_id' => isset($task['user_id']) ? \App\Models\Team::whereHas('users', fn($q) => $q->whereKey($task['user_id']))->value('organization_id') : null,
                    ]);
                }
                \App\Services\Notification\NotificationRouter::dispatch(
                    \App\Enums\Notification\NotificationType::AGENT_FAILED,
                    new \App\Services\Notification\NotificationContext(),
                    new \App\Notifications\AgentLifecycleNotification($this->agentService->getAgentId(), 'failed', 'Agent runtime task execution failed.')
                );
            }
            $this->queue->fail($task['id'], $e);
        } finally {
            \App\Services\TenantContext::clear();
        }

        return true;
    }
}
