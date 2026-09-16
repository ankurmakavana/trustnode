<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentWorker;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentTaskHandlerInterface;

class AgentWorkerTest extends TestCase
{
    protected AgentService $agentService;
    protected AgentQueueInterface $queue;
    protected AgentTaskHandlerRegistryInterface $registry;
    protected AgentWorker $worker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use mocks to test the generic worker logic without db
        $this->agentService = $this->createMock(AgentService::class);
        $this->queue = $this->createMock(AgentQueueInterface::class);
        $this->registry = $this->createMock(AgentTaskHandlerRegistryInterface::class);

        $this->agentService->method('getAgentId')->willReturn('agent-123');

        $this->worker = new AgentWorker(
            $this->agentService,
            $this->queue,
            $this->registry
        );
    }

    public function test_no_pending_task_returns_false()
    {
        $this->queue->expects($this->once())
            ->method('dequeue')
            ->with('agent-123')
            ->willReturn(null);

        $this->registry->expects($this->never())->method('resolve');
        
        $result = $this->worker->runOnce();
        
        $this->assertFalse($result);
    }

    public function test_known_registered_type_resolves_and_acks()
    {
        $task = [
            'id' => 'task-1',
            'type' => 'known.type',
            'payload' => ['a' => 1]
        ];

        $this->queue->expects($this->once())
            ->method('dequeue')
            ->willReturn($task);

        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($task);

        $this->registry->expects($this->once())
            ->method('resolve')
            ->with('known.type')
            ->willReturn($handler);

        $this->queue->expects($this->once())
            ->method('acknowledge')
            ->with('task-1');
            
        $this->queue->expects($this->never())
            ->method('fail');

        $result = $this->worker->runOnce();
        
        $this->assertTrue($result);
    }

    public function test_handler_runtime_failure_calls_fail()
    {
        $task = [
            'id' => 'task-2',
            'type' => 'error.type',
            'payload' => []
        ];

        $this->queue->expects($this->once())
            ->method('dequeue')
            ->willReturn($task);

        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        $exception = new \RuntimeException('Runtime error');
        $handler->expects($this->once())
            ->method('handle')
            ->willThrowException($exception);

        $this->registry->expects($this->once())
            ->method('resolve')
            ->willReturn($handler);

        $this->queue->expects($this->never())
            ->method('acknowledge');
            
        $this->queue->expects($this->once())
            ->method('fail')
            ->with('task-2', $exception);

        $result = $this->worker->runOnce();
        
        $this->assertTrue($result);
    }

    public function test_unknown_type_is_rejected_and_failed()
    {
        $task = [
            'id' => 'task-3',
            'type' => 'unknown.type',
            'payload' => []
        ];

        $this->queue->expects($this->once())
            ->method('dequeue')
            ->willReturn($task);

        $exception = new \InvalidArgumentException('Unknown type');
        $this->registry->expects($this->once())
            ->method('resolve')
            ->willThrowException($exception);

        $this->queue->expects($this->never())
            ->method('acknowledge');
            
        $this->queue->expects($this->once())
            ->method('fail')
            ->with('task-3', $exception);

        $result = $this->worker->runOnce();
        
        $this->assertTrue($result);
    }

    public function test_agent_isolation()
    {
        // Assert dequeue is only called with the current agent ID
        $this->queue->expects($this->once())
            ->method('dequeue')
            ->with('agent-123')
            ->willReturn(null);

        $this->worker->runOnce();
    }
}
