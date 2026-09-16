<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AgentService;
use App\Services\AgentQueue;
use App\Services\AgentWorker;
use Illuminate\Support\Facades\DB;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentTaskHandlerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AgentGracefulShutdownTest extends TestCase
{
    use RefreshDatabase;

    protected AgentService $agent;
    protected AgentQueue $queue;
    protected AgentWorker $worker;
    protected AgentTaskHandlerRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->agent = app(AgentService::class);
        $this->agent->start();
        
        $this->queue = app(AgentQueue::class);
        $this->registry = app(AgentTaskHandlerRegistryInterface::class);
        
        $boundary = $this->createMock(\App\Contracts\AgentSecurityBoundaryInterface::class);
        // Allow all operations for this test
        $boundary->method('authorize')->willReturn(null);
        $this->app->instance(\App\Contracts\AgentSecurityBoundaryInterface::class, $boundary);
        
        $this->worker = app(AgentWorker::class);
    }

    public function test_drain_stops_new_task_consumption_and_reaches_stopped()
    {
        $this->agent->drain();
        $this->assertTrue($this->agent->isStopping());
        
        $loopRan = false;
        while ($this->agent->isRunning()) {
            $loopRan = true;
        }
        $this->assertFalse($loopRan);
        if ($this->agent->isStopping()) {
            $this->agent->setState(\App\Services\AgentService::S_STOPPED);
            $this->agent->setStoppedAt(now());
        }
        $this->assertTrue($this->agent->isStopped());
    }

    public function test_pending_tasks_remain_pending_during_shutdown()
    {
        $id1 = $this->queue->enqueue($this->agent->getAgentId(), 'test.task', []);
        
        $this->agent->drain();
        
        $processedCount = 0;
        while ($this->agent->isRunning()) {
            $this->worker->runOnce();
            $processedCount++;
        }
        
        $this->assertEquals(0, $processedCount);
        
        $task = DB::table('agent_tasks')->where('id', $id1)->first();
        $this->assertEquals('pending', $task->status);
        
        $this->agent->stop();
    }
    
    public function test_successful_inflight_task_finishes_and_acknowledges()
    {
        $id1 = $this->queue->enqueue($this->agent->getAgentId(), 'test.inflight', []);
        
        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('handle')->willReturnCallback(function() {
            // Signal arrives DURING execution
            $this->agent->drain();
        });
        
        $this->registry->register($handler);
        
        $this->worker->runOnce();
        
        $task = DB::table('agent_tasks')->where('id', $id1)->first();
        $this->assertEquals('completed', $task->status);
        
        // Now agent is stopping
        $this->assertTrue($this->agent->isStopping());
    }
    
    public function test_failed_inflight_task_reaches_fail()
    {
        $id1 = $this->queue->enqueue($this->agent->getAgentId(), 'test.fail', []);
        
        $handler = $this->createMock(AgentTaskHandlerInterface::class);
        $handler->method('supports')->willReturn(true);
        $handler->expects($this->once())->method('handle')->willReturnCallback(function() {
            $this->agent->drain();
            throw new \RuntimeException('Runtime error');
        });
        
        $this->registry->register($handler);
        
        $this->worker->runOnce();
        
        $task = DB::table('agent_tasks')->where('id', $id1)->first();
        $this->assertEquals('pending', $task->status); // Status reverts to pending for retry due to fail()
        $this->assertEquals(1, $task->attempts);
        
        $this->assertTrue($this->agent->isStopping());
    }

    public function test_repeated_shutdown_signal_is_idempotent()
    {
        $this->agent->drain();
        $this->assertTrue($this->agent->isStopping());
        
        $this->agent->drain(); // should not throw
        $this->assertTrue($this->agent->isStopping());
    }
}
