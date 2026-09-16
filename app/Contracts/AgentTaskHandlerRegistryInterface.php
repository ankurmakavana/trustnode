<?php

namespace App\Contracts;

interface AgentTaskHandlerRegistryInterface
{
    /**
     * Register a handler instance.
     *
     * @param AgentTaskHandlerInterface $handler
     * @return void
     */
    public function register(AgentTaskHandlerInterface $handler): void;

    /**
     * Resolve a handler for the given task type.
     * 
     * @param string $type
     * @return AgentTaskHandlerInterface
     * @throws \InvalidArgumentException if no handler is found
     */
    public function resolve(string $type): AgentTaskHandlerInterface;
}
