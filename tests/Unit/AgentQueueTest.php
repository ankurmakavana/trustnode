<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Contracts\AgentQueueInterface;
use App\Services\AgentQueue;
use App\Exceptions\QueueFullException;
use App\Exceptions\InvalidPayloadException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;

class AgentQueueTest extends TestCase
{
    use RefreshDatabase;

    protected $queue;
    protected $agentId = 'test-agent-123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['agent.queue.max_size' => 3]); // small size for testing
        $this->queue = new AgentQueue();
    }

    public function test_enqueue_creates_pending_task_and_returns_identifier()
    {
        $taskId = $this->queue->enqueue($this->agentId, 'test_event', ['data' => 1]);
        $this->assertIsString($taskId);
        
        $this->assertDatabaseHas('agent_tasks', [
            'id' => $taskId,
            'agent_id' => $this->agentId,
            'type' => 'test_event',
            'status' => AgentQueue::STATUS_PENDING,
            'attempts' => 0
        ]);
        
        $this->assertEquals(1, $this->queue->size($this->agentId));
    }

    public function test_fifo_ordering_and_isolation()
    {
        $otherAgent = 'other-agent-456';
        
        $id1 = $this->queue->enqueue($this->agentId, 'type1', ['o' => 1]);
        $this->queue->enqueue($otherAgent, 'typeX', ['o' => 'X']);
        $id2 = $this->queue->enqueue($this->agentId, 'type2', ['o' => 2]);
        
        $task1 = $this->queue->dequeue($this->agentId);
        $this->assertNotNull($task1);
        $this->assertEquals($id1, $task1['id']);
        $this->assertEquals(AgentQueue::STATUS_PROCESSING, $task1['status']);
        
        $task2 = $this->queue->dequeue($this->agentId);
        $this->assertNotNull($task2);
        $this->assertEquals($id2, $task2['id']);
        
        // No more tasks for this agent
        $this->assertNull($this->queue->dequeue($this->agentId));
        
        // Other agent is isolated
        $taskX = $this->queue->dequeue($otherAgent);
        $this->assertEquals('typeX', $taskX['type']);
    }

    public function test_acknowledge_changes_processing_to_completed()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $this->queue->acknowledge($taskId);
        
        $this->assertDatabaseHas('agent_tasks', [
            'id' => $taskId,
            'status' => AgentQueue::STATUS_COMPLETED
        ]);
        
        // Completed tasks don't count towards size
        $this->assertEquals(0, $this->queue->size($this->agentId));
    }

    public function test_acknowledge_rejects_invalid_state_transition()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        
        $this->expectException(LogicException::class);
        $this->queue->acknowledge($taskId); // Still pending
    }

    public function test_fail_changes_processing_to_failed_and_increments_attempts()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $this->queue->fail($taskId);
        
        $this->assertDatabaseHas('agent_tasks', [
            'id' => $taskId,
            'status' => AgentQueue::STATUS_FAILED,
            'attempts' => 1
        ]);
        
        // Failed tasks DO NOT count towards size (they are no longer pending or processing)
        // Wait, does the size() include failed? 
        // The implementation counts PENDING and PROCESSING. So it will be 0.
        $this->assertEquals(0, $this->queue->size($this->agentId));
    }

    public function test_queue_full_throws_exception_and_completed_tasks_do_not_consume_capacity()
    {
        $this->queue->enqueue($this->agentId, 't1', []);
        $this->queue->enqueue($this->agentId, 't2', []);
        $id3 = $this->queue->enqueue($this->agentId, 't3', []);
        
        $this->assertTrue($this->queue->isFull($this->agentId));
        
        $this->expectException(QueueFullException::class);
        try {
            $this->queue->enqueue($this->agentId, 't4', []);
        } catch (QueueFullException $e) {
            // Now free up space
            $this->queue->dequeue($this->agentId); // dequeues t1
            $this->queue->dequeue($this->agentId); // dequeues t2
            $this->queue->dequeue($this->agentId); // dequeues t3
            
            $this->queue->acknowledge($id3);
            
            $this->assertFalse($this->queue->isFull($this->agentId));
            $this->queue->enqueue($this->agentId, 't4', []); // Should succeed now
            throw $e; // Throw anyway for the expectException to catch it
        }
    }

    public function test_invalid_and_forbidden_payload_is_rejected()
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Payload contains forbidden sensitive/executable key: command');
        $this->queue->enqueue($this->agentId, 't', ['safe' => 1, 'nested' => ['command' => 'rm -rf']]);
    }

    public function test_oversized_payload_rejected()
    {
        // 65535 limit
        $largeString = str_repeat('a', 70000);
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Payload size exceeds');
        $this->queue->enqueue($this->agentId, 't', ['data' => $largeString]);
    }
    
    public function test_processing_task_remains_persisted_if_consumer_disappears()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $task = $this->queue->dequeue($this->agentId);
        
        // Simulating crash: neither ack nor fail is called.
        // We just re-instantiate AgentQueue.
        $newQueue = new AgentQueue();
        
        // The task is still stuck in 'processing', so it doesn't get dequeued
        $this->assertNull($newQueue->dequeue($this->agentId));
        
        // But it still counts towards capacity
        $this->assertEquals(1, $newQueue->size($this->agentId));
    }
}
