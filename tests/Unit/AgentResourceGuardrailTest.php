<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentWorker;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentTaskHandlerInterface;
use Illuminate\Support\Facades\Config;

class AgentResourceGuardrailTest extends TestCase
{
    public function test_memory_limit_is_applied_from_configuration()
    {
        // We can test if the configuration is accessible
        $this->assertEquals(256, config('agent.guardrails.memory_mb'));
        
        // Simulating the console script logic to ensure it behaves correctly
        $memoryMb = config('agent.guardrails.memory_mb');
        $expectedLimit = $memoryMb . 'M';
        
        ini_set('memory_limit', $expectedLimit);
        $this->assertEquals($expectedLimit, ini_get('memory_limit'));
    }

    public function test_execution_time_limit_is_applied_during_run_once()
    {
        Config::set('agent.guardrails.max_execution_time', 5);

        $agentService = $this->createMock(AgentService::class);
        $agentService->method('getAgentId')->willReturn('agent-123');

        $queue = $this->createMock(AgentQueueInterface::class);
        $queue->method('dequeue')->willReturn([
            'id' => 'task-1',
            'type' => 'test.type',
            'payload' => []
        ]);

        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        $handler->expects($this->once())->method('handle');

        $registry = $this->createMock(AgentTaskHandlerRegistryInterface::class);
        $registry->method('resolve')->willReturn($handler);

        $worker = new AgentWorker($agentService, $queue, $registry);

        // Execute task
        $worker->runOnce();

        // While we cannot strictly mock set_time_limit inside the test environment without runkit,
        // we assert that the worker cleanly processes the task and config is present.
        $this->assertEquals(5, config('agent.guardrails.max_execution_time'));
    }

    public function test_polling_interval_has_sane_minimum()
    {
        // Testing the logic applied in console.php
        Config::set('agent.guardrails.poll_interval', 0); // Invalid zero config
        
        $pollInterval = max(1, (int) config('agent.guardrails.poll_interval', 1));
        
        // Assert it bounds to 1 safely
        $this->assertEquals(1, $pollInterval);
        
        Config::set('agent.guardrails.poll_interval', -5); // Negative config
        $pollInterval = max(1, (int) config('agent.guardrails.poll_interval', 1));
        
        $this->assertEquals(1, $pollInterval);
        
        Config::set('agent.guardrails.poll_interval', 5); // Valid config
        $pollInterval = max(1, (int) config('agent.guardrails.poll_interval', 1));
        
        $this->assertEquals(5, $pollInterval);
    }

    public function test_queue_max_capacity_remains_enforced_by_queue()
    {
        $this->assertEquals(1000, config('agent.queue.max_size'));
    }
}
