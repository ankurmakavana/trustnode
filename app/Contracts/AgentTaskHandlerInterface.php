<?php

namespace App\Contracts;

interface AgentTaskHandlerInterface
{
    /**
     * Determine if this handler supports the given task type.
     *
     * @param string $type
     * @return bool
     */
    public function supports(string $type): bool;

    /**
     * Execute the task logic.
     * 
     * @param array $task The complete structured task from the queue
     * @return void
     * @throws \Throwable
     */
    public function handle(array $task): void;
}
