<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AgentApproval;
use App\Services\AgentApprovalService;
use App\Services\AgentCapabilityRegistry;
use App\Exceptions\AgentSecurityException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class AgentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected AgentCapabilityRegistry $registry;
    protected AgentApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new AgentCapabilityRegistry();
        $this->service = new AgentApprovalService($this->registry);
    }

    public function test_approval_request_can_be_created_for_valid_context()
    {
        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: Approval pending for operation [test.op].");

        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 1]);
        
        $this->assertDatabaseHas('agent_approvals', [
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'status' => 'pending'
        ]);
    }

    public function test_pending_approval_cannot_authorize_execution()
    {
        AgentApproval::create([
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'capability' => 'agent.cap',
            'scope' => ['arg' => 1],
            'request_fingerprint' => hash('sha256', "agent-1:test.op:agent.cap:" . json_encode(['arg' => 1])),
            'status' => 'pending',
        ]);

        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: Approval status is [pending].");

        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 1]);
    }

    public function test_approved_approval_can_authorize_only_its_exact_context()
    {
        $approval = AgentApproval::create([
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'capability' => 'agent.cap',
            'scope' => ['arg' => 1],
            'request_fingerprint' => hash('sha256', "agent-1:test.op:agent.cap:" . json_encode(['arg' => 1])),
            'status' => 'pending',
        ]);

        $this->service->approve($approval->id, 1);

        // This should pass and add a grant
        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 1]);
        
        $grants = $this->registry->getGrants('agent-1', 'agent.cap');
        $this->assertCount(1, $grants);
    }

    public function test_changed_request_fingerprint_invalidates_approval()
    {
        $approval = AgentApproval::create([
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'capability' => 'agent.cap',
            'scope' => ['arg' => 1],
            'request_fingerprint' => hash('sha256', "agent-1:test.op:agent.cap:" . json_encode(['arg' => 1])),
            'status' => 'approved',
        ]);

        // Same agent and operation, but different args -> new fingerprint -> creates new pending request
        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: Approval pending for operation [test.op].");

        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 2]);
    }

    public function test_expired_approval_cannot_authorize_execution()
    {
        $approval = AgentApproval::create([
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'capability' => 'agent.cap',
            'scope' => ['arg' => 1],
            'request_fingerprint' => hash('sha256', "agent-1:test.op:agent.cap:" . json_encode(['arg' => 1])),
            'status' => 'approved',
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: Approval has expired.");

        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 1]);
    }

    public function test_revoked_approval_cannot_authorize_execution()
    {
        $approval = AgentApproval::create([
            'agent_id' => 'agent-1',
            'operation' => 'test.op',
            'capability' => 'agent.cap',
            'scope' => ['arg' => 1],
            'request_fingerprint' => hash('sha256', "agent-1:test.op:agent.cap:" . json_encode(['arg' => 1])),
            'status' => 'pending',
        ]);

        $this->service->approve($approval->id, 1);
        $this->service->revoke($approval->id, 2);

        $this->expectException(AgentSecurityException::class);
        $this->expectExceptionMessage("Execution denied: Approval status is [revoked].");

        $this->service->authorizeRequest('agent-1', 'test.op', 'agent.cap', ['arg' => 1]);
    }
}
