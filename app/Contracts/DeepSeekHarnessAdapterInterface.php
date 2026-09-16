<?php

namespace App\Contracts;

interface DeepSeekHarnessAdapterInterface
{
    public function execute(
        string $agentId,
        string $executionId,
        string $operation,
        array $arguments,
        array $executionContext = []
    ): DeepSeekHarnessExecutionResultInterface;
}
