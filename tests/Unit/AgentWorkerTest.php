<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentWorker;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\AgentSecurityBoundaryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AgentWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected AgentService $agentService;
    protected AgentQueueInterface $queue;
    protected AgentTaskHandlerRegistryInterface $registry;
    protected AgentWorker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Notification::fake();

        // Use mocks to test the generic worker logic without db
        $this->agentService = $this->createMock(AgentService::class);
        $this->queue = $this->createMock(AgentQueueInterface::class);
        $this->registry = $this->createMock(AgentTaskHandlerRegistryInterface::class);
        $this->boundary = $this->createMock(AgentSecurityBoundaryInterface::class);

        $this->agentService->method('getAgentId')->willReturn('agent-123');

        $this->worker = new AgentWorker(
            $this->agentService,
            $this->queue,
            $this->registry,
            $this->boundary
        );

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $role = \App\Models\Role::first();
        \App\Models\User::factory()->create(['id' => 1, 'role_id' => $role->id]);
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
            'user_id' => 1,
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
            'user_id' => 1,
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
            'user_id' => 1,
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

    public function test_secret_redaction_in_activity_logs()
    {
        $this->registry->method('resolve')->willThrowException(new \RuntimeException("Connection failed: Bearer secret-token-123 API_KEY=abcxyz password=hidden"));

        $this->queue->expects($this->once())
            ->method('dequeue')
            ->willReturn([
                'id' => 'task-secret',
                'type' => 'agent.scan_codebase',
                'payload' => '{}',
                'attempts' => 0,
                'user_id' => 1
            ]);

        \Illuminate\Support\Facades\Cache::flush();
        $this->worker->runOnce();

        $log = \App\Models\AgentActivityLog::withoutGlobalScopes()->where('agent_id', 'agent-123')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('secret-token-123', $log->properties['error']);
        $this->assertStringNotContainsString('abcxyz', $log->properties['error']);
        $this->assertStringNotContainsString('hidden', $log->properties['error']);
        $this->assertStringContainsString('Bearer ***', $log->properties['error']);
        // Shared placeholder from SecretRedactor (previously '***'); the secret values above are still absent.
        $this->assertStringContainsString('API_KEY=[REDACTED]', $log->properties['error']);
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

    public function test_tenant_isolation_excludes_null_organizations()
    {
        $registry = $this->createMock(\App\Contracts\AgentTaskHandlerRegistryInterface::class);
        $queue = $this->createMock(\App\Contracts\AgentQueueInterface::class);
        $agentService = $this->createMock(\App\Services\AgentService::class);
        $boundary = $this->createMock(\App\Contracts\AgentSecurityBoundaryInterface::class);

        $agentService->method('getAgentId')->willReturn('agent-tenant-test');

        // Create log with NULL organization
        \App\Models\AgentActivityLog::create([
            'agent_id' => 'agent-tenant-test',
            'action' => 'started',
            'properties' => [],
            'organization_id' => null,
            'user_id' => null,
        ]);

        $adminRole = \App\Models\Role::firstOrCreate(['slug' => \App\Enums\UserRole::ADMINISTRATOR->value], ['name' => 'Admin']);
        $user = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($user);

        // Fetch logs (TenantScope is automatically applied)
        $logs = \App\Models\AgentActivityLog::where('agent_id', 'agent-tenant-test')->get();

        $this->assertCount(0, $logs, "AgentActivityLog with NULL organization MUST be hidden from tenant queries.");
    }

    public function test_tenant_isolation_prevents_cross_tenant_access()
    {
        $orgA = \App\Models\Organization::factory()->create();
        $orgB = \App\Models\Organization::factory()->create();

        $adminRole = \App\Models\Role::firstOrCreate(['slug' => \App\Enums\UserRole::ADMINISTRATOR->value], ['name' => 'Admin']);

        $userA = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);
        $teamA = \App\Models\Team::factory()->create(['organization_id' => $orgA->id]);
        $userA->teams()->attach($teamA->id);

        $userB = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);
        $teamB = \App\Models\Team::factory()->create(['organization_id' => $orgB->id]);
        $userB->teams()->attach($teamB->id);

        // Create log for Organization A
        \App\Models\AgentActivityLog::create([
            'agent_id' => 'agent-tenant-test',
            'action' => 'started',
            'properties' => [],
            'organization_id' => $orgA->id,
            'user_id' => $userA->id,
        ]);

        // User B attempts to view logs
        $this->actingAs($userB);

        // Fetch logs (TenantScope is automatically applied)
        $logs = \App\Models\AgentActivityLog::where('agent_id', 'agent-tenant-test')->get();

        $this->assertCount(0, $logs, "User B MUST NOT see AgentActivityLog belonging to Organization A.");
    }
}
