<?php

namespace App\Contracts;

use Throwable;

interface AgentQueueInterface
{
    /**
     * Enqueue a new task for the given agent.
     *
     * @param string $agentId
     * @param string $type
     * @param array $payload
     * @return string Task ID
     * @throws \App\Exceptions\QueueFullException
     * @throws \App\Exceptions\InvalidPayloadException
     */
    public function enqueue(string $agentId, string $type, array $payload): string;

    /**
     * Atomically claim and dequeue the next pending task for the given agent.
     *
     * @param string $agentId
     * @return array|null The task array (with id, type, payload, status, etc.)
     */
    public function dequeue(string $agentId): ?array;

    /**
     * Acknowledge that a processing task has completed.
     *
     * @param string $taskId
     * @return void
     * @throws \LogicException If the task does not exist or is not processing.
     */
    public function acknowledge(string $taskId): void;

    /**
     * Mark a processing task as failed.
     *
     * @param string $taskId
     * @param Throwable|null $exception
     * @return void
     * @throws \LogicException If the task does not exist or is not processing.
     */
    public function fail(string $taskId, ?Throwable $exception = null): void;

    /**
     * Get the number of active (pending + processing) tasks for the agent.
     *
     * @param string $agentId
     * @return int
     */
    public function size(string $agentId): int;

    /**
     * Check if the queue is full for the agent.
     *
     * @param string $agentId
     * @return bool
     */
    public function isFull(string $agentId): bool;

    /**
     * Check if a task of the given type is already pending or processing.
     *
     * @param string $agentId
     * @param string $type
     * @return bool
     */
    public function hasTaskType(string $agentId, string $type): bool;
}
