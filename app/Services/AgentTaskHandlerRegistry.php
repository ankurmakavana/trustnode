<?php

namespace App\Services;

use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use InvalidArgumentException;

class AgentTaskHandlerRegistry implements AgentTaskHandlerRegistryInterface
{
    protected array $handlers = [];

    public function register(AgentTaskHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function resolve(string $type): AgentTaskHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        throw new InvalidArgumentException("No registered handler supports task type: [{$type}]");
    }
}
