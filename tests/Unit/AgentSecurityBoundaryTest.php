<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentWorker;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\AgentSecurityBoundaryInterface;
use App\Exceptions\AgentSecurityException;
use App\Services\AgentSecurityBoundary;
use Illuminate\Support\Facades\Log;

class AgentSecurityBoundaryTest extends TestCase
{
    public function test_security_boundary_denies_execution_by_default()
    {
        $boundary = new AgentSecurityBoundary();
        
        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: operation [test.op] is not authorized.");
        
        $boundary->authorize('agent-123', 'test.op', ['arg1' => 'val1']);
    }

    public function test_worker_fails_task_if_security_boundary_denies()
    {
        $agentService = $this->createMock(AgentService::class);
        $agentService->method('getAgentId')->willReturn('agent-123');

        $queue = $this->createMock(AgentQueueInterface::class);
        $queue->method('dequeue')->willReturn([
            'id' => 'task-1',
            'type' => 'test.type',
            'payload' => []
        ]);
        
        // Ensure fail is called, not acknowledge
        $queue->expects($this->once())->method('fail')->with('task-1', $this->isInstanceOf(AgentSecurityException::class));
        $queue->expects($this->never())->method('acknowledge');

        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        // Handler should never be called
        $handler->expects($this->never())->method('handle');

        $registry = $this->createMock(AgentTaskHandlerRegistryInterface::class);
        $registry->method('resolve')->willReturn($handler);
        
        $boundary = $this->createMock(AgentSecurityBoundaryInterface::class);
        $boundary->expects($this->once())
            ->method('authorize')
            ->willThrowException(new AgentSecurityException("Denied"));

        $worker = new AgentWorker($agentService, $queue, $registry, $boundary);

        // Execute task
        $worker->runOnce();
    }
    
    public function test_worker_executes_handler_if_security_boundary_allows()
    {
        $agentService = $this->createMock(AgentService::class);
        $agentService->method('getAgentId')->willReturn('agent-123');

        $queue = $this->createMock(AgentQueueInterface::class);
        $queue->method('dequeue')->willReturn([
            'id' => 'task-1',
            'type' => 'test.type',
            'payload' => []
        ]);
        
        // Ensure acknowledge is called
        $queue->expects($this->once())->method('acknowledge')->with('task-1');
        $queue->expects($this->never())->method('fail');

        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        // Handler should be called
        $handler->expects($this->once())->method('handle');

        $registry = $this->createMock(AgentTaskHandlerRegistryInterface::class);
        $registry->method('resolve')->willReturn($handler);
        
        $boundary = $this->createMock(AgentSecurityBoundaryInterface::class);
        $boundary->expects($this->once())
            ->method('authorize')
            ->with('agent-123', 'test.type', []);

        $worker = new AgentWorker($agentService, $queue, $registry, $boundary);

        // Execute task
        $worker->runOnce();
    }
}
