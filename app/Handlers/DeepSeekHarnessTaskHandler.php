<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\DeepSeekHarnessAdapterInterface;
use Illuminate\Support\Facades\Log;

class DeepSeekHarnessTaskHandler implements AgentTaskHandlerInterface
{
    protected DeepSeekHarnessAdapterInterface $adapter;

    public function __construct(DeepSeekHarnessAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    public function supports(string $type): bool
    {
        return $type === 'deepseek_harness_execution';
    }

    public function handle(array $task): void
    {
        $agentId = $task['agent_id'] ?? 'unknown';
        $executionId = $task['id'] ?? uniqid('exec_', true);
        $operation = $task['type'] ?? 'unknown';
        $payload = $task['payload'] ?? [];
        
        $context = [
            'task_id' => $task['id'] ?? null,
        ];

        $result = $this->adapter->execute($agentId, $executionId, $operation, $payload, $context);

        if (!$result->isSuccess()) {
            if ($result->isCancelled()) {
                throw new \RuntimeException("Harness execution cancelled: " . $result->getError());
            }
            throw new \RuntimeException("Harness execution failed: " . $result->getError());
        }

        Log::info('DeepSeekHarnessTaskHandler: execution successful', [
            'execution_id' => $executionId,
            'output' => $result->getOutput()
        ]);
    }
}
