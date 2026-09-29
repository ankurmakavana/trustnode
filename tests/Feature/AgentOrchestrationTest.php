<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Contracts\AgentCapabilityRegistryInterface;
use App\Contracts\AgentTaskHandlerRegistryInterface;
use App\Contracts\AgentQueueInterface;
use App\Contracts\AgentApprovalServiceInterface;
use App\Services\AgentService;
use App\Models\AgentApproval;
use Illuminate\Support\Facades\Schema;

class AgentOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    protected \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->first();
        $this->user = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($this->user);
    }

    public function test_capability_registry_resolves_capability()
    {
        $registry = $this->app->make(AgentCapabilityRegistryInterface::class);
        $capability = $registry->resolve('agent:scan_codebase');
        $this->assertNotNull($capability);
        $this->assertEquals('agent:scan_codebase', $capability->getId());
    }

    public function test_agent_scan_codebase_invokes_repository_scanner_and_creates_records()
    {
        $agentService = $this->app->make(AgentService::class);
        $agentService->start();

        $registry = $this->app->make(AgentCapabilityRegistryInterface::class);
        $capability = $registry->resolve('agent:scan_codebase');
        
        $targetPath = base_path('tests');
        $result = $capability->execute(['target_path' => $targetPath], ['agent_id' => $agentService->getAgentId()]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('scan_id', $result);
        $this->assertArrayHasKey('findings_count', $result);

        if (Schema::hasTable('scans')) {
            $this->assertDatabaseHas('scans', [
                'id' => $result['scan_id'],
                'target' => realpath(base_path('tests')),
                'status' => 'completed'
            ]);
        }
    }

    public function test_planner_selects_scan_codebase_for_codebase_audit_objective()
    {
        $agentService = $this->app->make(AgentService::class);
        $agentService->start(); // Bootstraps agent_states table row

        $queue = $this->app->make(AgentQueueInterface::class);
        $handlerRegistry = $this->app->make(AgentTaskHandlerRegistryInterface::class);
        $planner = $handlerRegistry->resolve('agent.plan');

        $planner->handle([
            'id' => 'plan_task_1',
            'agent_id' => $agentService->getAgentId(),
            'type' => 'agent.plan',
            'payload' => [
                'objective' => 'Audit the current TrustNode codebase',
                'step' => 1
            ]
        ]);

        $task = $queue->dequeue($agentService->getAgentId());
        $this->assertNotNull($task);
        $this->assertEquals('agent.capability.execute', $task['type']);
        $this->assertEquals('agent:scan_codebase', $task['payload']['capability_id']);
    }

    public function test_permission_required_capability_enters_waiting_approval()
    {
        $worker = $this->app->make(\App\Services\AgentWorker::class);
        $queue = $this->app->make(AgentQueueInterface::class);
        $agentService = $this->app->make(AgentService::class);
        $agentService->start(); // Running state

        $queue->enqueue($agentService->getAgentId(), 'agent.capability.execute', [
            'capability_id' => 'agent:restricted_action',
            'arguments' => ['action' => 'delete']
        ]);

        $worker->runOnce();

        $this->assertEquals(AgentService::S_WAITING_APPROVAL, $agentService->getState());

        $approval = AgentApproval::where('agent_id', $agentService->getAgentId())->first();
        $this->assertNotNull($approval);
        $this->assertEquals('pending', $approval->status);
    }

    public function test_approval_resumes_execution()
    {
        $worker = $this->app->make(\App\Services\AgentWorker::class);
        $queue = $this->app->make(AgentQueueInterface::class);
        $agentService = $this->app->make(AgentService::class);
        $agentId = $agentService->getAgentId();
        $agentService->start();

        // 1. Initial attempt fails and pauses
        $taskId = $queue->enqueue($agentId, 'agent.capability.execute', [
            'capability_id' => 'agent:restricted_action',
            'arguments' => ['action' => 'delete'],
            'plan_task_id' => 'parent_plan'
        ]);

        $worker->runOnce(); // Will fail and enter waiting_approval
        
        $approval = AgentApproval::where('agent_id', $agentId)->first();
        $this->assertNotNull($approval);
        
        // 2. User approves
        $approvalService = $this->app->make(AgentApprovalServiceInterface::class);
        $approvalService->approve($approval->id);

        // 3. System requeues (simulate)
        $queue->enqueue($agentId, 'agent.capability.execute', [
            'capability_id' => 'agent:restricted_action',
            'arguments' => ['action' => 'delete'],
            'plan_task_id' => 'parent_plan'
        ]);

        // 4. Worker executes successfully and queues next plan step
        $worker->runOnce();

        $nextTask = $queue->dequeue($agentId);
        $this->assertNotNull($nextTask);
        $this->assertEquals('agent.plan', $nextTask['type']);
        $this->assertEquals('agent:restricted_action', $nextTask['payload']['last_action']);
        $this->assertTrue($nextTask['payload']['last_result']['success']);
    }
}
