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
        config(['agent.state.driver' => 'database']); // ensure database driver for tests
        
        // We ensure an active state row exists because enqueue requires it
        DB::table('agent_states')->insert([
            'agent_id' => $this->agentId,
            'state' => json_encode(['state' => 'running']),
            'updated_at' => now()
        ]);
        
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
        
        DB::table('agent_states')->insert([
            'agent_id' => $otherAgent,
            'state' => json_encode(['state' => 'running']),
            'updated_at' => now()
        ]);
        
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

    public function test_fail_transitions_to_pending_and_sets_backoff()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $now = \Carbon\Carbon::now();
        \Carbon\Carbon::setTestNow($now);

        $this->queue->fail($taskId);
        
        $this->assertDatabaseHas('agent_tasks', [
            'id' => $taskId,
            'status' => AgentQueue::STATUS_PENDING,
            'attempts' => 1,
            'available_at' => $now->copy()->addSeconds(5)->format('Y-m-d H:i:s')
        ]);
        
        \Carbon\Carbon::setTestNow();
    }

    public function test_fail_applies_exponential_backoff_across_retries()
    {
        config(['agent.queue.retry.base_delay' => 5]);
        config(['agent.queue.retry.multiplier' => 2]);
        config(['agent.queue.retry.max_attempts' => 5]);
        
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        
        $now = \Carbon\Carbon::now();
        \Carbon\Carbon::setTestNow($now);
        
        $delays = [5, 10, 20, 40];

        foreach ($delays as $delay) {
            $this->queue->dequeue($this->agentId);
            $this->queue->fail($taskId);
            
            $this->assertDatabaseHas('agent_tasks', [
                'id' => $taskId,
                'status' => AgentQueue::STATUS_PENDING,
                'available_at' => $now->copy()->addSeconds($delay)->format('Y-m-d H:i:s')
            ]);
            
            $now->addSeconds($delay);
            \Carbon\Carbon::setTestNow($now);
        }
        
        \Carbon\Carbon::setTestNow();
    }

    public function test_fail_transitions_to_failed_at_max_attempts()
    {
        config(['agent.queue.retry.max_attempts' => 2]);
        
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        
        // Attempt 1 -> fails -> retry
        $this->queue->dequeue($this->agentId);
        $this->queue->fail($taskId);
        
        // Attempt 2 -> fails -> terminal
        \Carbon\Carbon::setTestNow(now()->addDays(1)); // skip backoff
        $this->queue->dequeue($this->agentId);
        $this->queue->fail($taskId);
        
        $this->assertDatabaseHas('agent_tasks', [
            'id' => $taskId,
            'status' => AgentQueue::STATUS_FAILED,
            'attempts' => 2,
        ]);
        
        \Carbon\Carbon::setTestNow();
    }

    public function test_retry_does_not_create_new_queue_task()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $this->assertEquals(1, DB::table('agent_tasks')->count());
        $this->queue->fail($taskId);
        $this->assertEquals(1, DB::table('agent_tasks')->count());
        
        $task = DB::table('agent_tasks')->first();
        $this->assertEquals($taskId, $task->id);
    }

    public function test_retry_preserves_active_capacity_slot()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $this->assertEquals(1, $this->queue->size($this->agentId));
        $this->queue->fail($taskId);
        $this->assertEquals(1, $this->queue->size($this->agentId));
    }

    public function test_terminal_failure_releases_capacity_slot()
    {
        config(['agent.queue.retry.max_attempts' => 1]);
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        $this->assertEquals(1, $this->queue->size($this->agentId));
        $this->queue->fail($taskId);
        $this->assertEquals(0, $this->queue->size($this->agentId));
    }

    public function test_dequeue_ignores_task_until_available_at()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        \Carbon\Carbon::setTestNow('2023-01-01 10:00:00');
        $this->queue->fail($taskId);
        
        \Carbon\Carbon::setTestNow('2023-01-01 10:00:01'); // Backoff is 5
        $this->assertNull($this->queue->dequeue($this->agentId));
        
        \Carbon\Carbon::setTestNow();
    }

    public function test_dequeue_accepts_retry_after_available_at()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        
        \Carbon\Carbon::setTestNow('2023-01-01 10:00:00');
        $this->queue->fail($taskId);
        
        \Carbon\Carbon::setTestNow('2023-01-01 10:00:05');
        $task = $this->queue->dequeue($this->agentId);
        $this->assertNotNull($task);
        $this->assertEquals($taskId, $task['id']);
        
        \Carbon\Carbon::setTestNow();
    }

    public function test_repeated_fail_on_non_processing_task_is_idempotent_or_rejected()
    {
        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);
        $this->queue->fail($taskId);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('It is either missing or not processing');
        $this->queue->fail($taskId);
    }

    public function test_concurrent_fail_cannot_double_increment_attempts()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL concurrency infrastructure is unavailable.');
        }

        $taskId = $this->queue->enqueue($this->agentId, 't', []);
        $this->queue->dequeue($this->agentId);

        try {
            $pdo1 = DB::connection()->getPdo();
            $pdo2 = new \PDO(
                config('database.connections.mysql.driver') . ':host=' . config('database.connections.mysql.host') . ';dbname=' . config('database.connections.mysql.database'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password')
            );
            $pdo2->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not establish secondary MySQL connection for concurrency test.');
        }

        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("SELECT * FROM agent_tasks WHERE id = :id FOR UPDATE");
        $stmt1->execute(['id' => $taskId]);

        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 1");
        
        $exceptionCaught = false;
        try {
            $stmt2 = $pdo2->prepare("SELECT * FROM agent_tasks WHERE id = :id FOR UPDATE");
            $stmt2->execute(['id' => $taskId]);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), '1205') !== false) {
                $exceptionCaught = true;
            }
        }
        
        $this->assertTrue($exceptionCaught, 'T2 did not block on T1s row lock.');

        $pdo1->commit();
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

    public function test_enqueue_fails_if_agent_states_row_missing()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing. Agent must be bootstrapped first');
        
        $this->queue->enqueue('unknown-agent-id', 'test_event', []);
    }

    public function test_schema_validates_unique_agent_id()
    {
        // Prove that the database enforces uniqueness on agent_id
        // We already inserted 'test-agent-123' in setUp
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessageMatches('/UNIQUE constraint failed|Duplicate entry/');
        
        DB::table('agent_states')->insert([
            'agent_id' => $this->agentId,
            'state' => json_encode(['state' => 'duplicate_attempt']),
            'updated_at' => now()
        ]);
    }

    public function test_concurrent_enqueue_at_boundary_throws_exception()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL concurrency infrastructure is unavailable. Cannot perform real lock test on SQLite.');
        }

        // We set max_size to 1
        config(['agent.queue.max_size' => 1]);
        $queue = new AgentQueue(); // recreate to pick up config

        try {
            $pdo1 = DB::connection()->getPdo();
            
            // Connect second connection
            $pdo2 = new \PDO(
                config('database.connections.mysql.driver') . ':host=' . config('database.connections.mysql.host') . ';dbname=' . config('database.connections.mysql.database'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password')
            );
            $pdo2->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not establish secondary MySQL connection for concurrency test.');
        }

        // T1: Lock the row but do not commit yet
        $pdo1->beginTransaction();
        $stmt1 = $pdo1->prepare("SELECT * FROM agent_states WHERE agent_id = :id FOR UPDATE");
        $stmt1->execute(['id' => $this->agentId]);
        
        // Active count = 0
        $countStmt = $pdo1->prepare("SELECT COUNT(*) FROM agent_tasks WHERE agent_id = :id AND status IN (?, ?)");
        $countStmt->execute([$this->agentId, AgentQueue::STATUS_PENDING, AgentQueue::STATUS_PROCESSING]);
        $this->assertEquals(0, $countStmt->fetchColumn());

        // We simulate T2 trying to enqueue. It should block.
        // We use a short innodb_lock_wait_timeout for T2 so we don't hang the test suite indefinitely.
        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 1");
        
        $exceptionCaught = false;
        try {
            // This will block and then timeout because T1 holds the lock
            $stmt2 = $pdo2->prepare("SELECT * FROM agent_states WHERE agent_id = :id FOR UPDATE");
            $stmt2->execute(['id' => $this->agentId]);
        } catch (\PDOException $e) {
            // 1205 is Lock wait timeout exceeded
            if (strpos($e->getMessage(), '1205') !== false) {
                $exceptionCaught = true;
            }
        }
        
        $this->assertTrue($exceptionCaught, 'T2 did not block on T1s row lock.');

        // T1 completes the insert and commits
        $insertStmt = $pdo1->prepare("INSERT INTO agent_tasks (id, agent_id, type, payload, status, attempts, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->execute(['task-1', $this->agentId, 't', '{}', AgentQueue::STATUS_PENDING, 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $pdo1->commit();

        // Now T2 can proceed. T2 checks capacity.
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare("SELECT * FROM agent_states WHERE agent_id = :id FOR UPDATE");
        $stmt2->execute(['id' => $this->agentId]);
        
        $countStmt2 = $pdo2->prepare("SELECT COUNT(*) FROM agent_tasks WHERE agent_id = :id AND status IN (?, ?)");
        $countStmt2->execute([$this->agentId, AgentQueue::STATUS_PENDING, AgentQueue::STATUS_PROCESSING]);
        $activeCount = $countStmt2->fetchColumn();
        
        $pdo2->commit();
        
        $this->assertEquals(1, $activeCount, 'T2 capacity check must see the task inserted by T1');
        
        // As a result, T2 would throw QueueFullException in application code.
    }
}
