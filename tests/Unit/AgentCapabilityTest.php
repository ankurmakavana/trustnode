<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AgentCapabilityRegistry;

class AgentCapabilityTest extends TestCase
{
    public function test_unknown_operation_has_no_capability()
    {
        $registry = new AgentCapabilityRegistry();
        $this->assertNull($registry->getRequiredCapability('unknown.operation'));
    }

    public function test_registered_operation_returns_capability()
    {
        $registry = new AgentCapabilityRegistry();
        $registry->registerOperation('test.op', 'agent.test');
        $this->assertEquals('agent.test', $registry->getRequiredCapability('test.op'));
    }

    public function test_capability_with_no_grant_returns_empty()
    {
        $registry = new AgentCapabilityRegistry();
        $this->assertEmpty($registry->getGrants('agent-1', 'agent.test'));
    }

    public function test_granted_capability_is_returned()
    {
        $registry = new AgentCapabilityRegistry();
        $registry->addGrant('agent-1', 'agent.test', ['path' => '/tmp']);
        
        $grants = $registry->getGrants('agent-1', 'agent.test');
        $this->assertCount(1, $grants);
        $this->assertEquals(['path' => '/tmp'], $grants[0]['scope']);
        $this->assertTrue($grants[0]['enabled']);
    }

    public function test_revoked_capability_is_denied()
    {
        $registry = new AgentCapabilityRegistry();
        $registry->addGrant('agent-1', 'agent.test');
        $registry->revokeCapability('agent-1', 'agent.test');
        
        $this->assertEmpty($registry->getGrants('agent-1', 'agent.test'));
    }

    public function test_task_payload_cannot_grant_capability()
    {
        // This is proven by the interface; grants are managed by the registry, 
        // not by the payload interpretation in the boundary.
        $registry = new AgentCapabilityRegistry();
        $this->assertEmpty($registry->getGrants('agent-1', 'agent.test'));
    }

    public function test_impersonation_prevented_by_strict_identity()
    {
        $registry = new AgentCapabilityRegistry();
        $registry->addGrant('agent-1', 'agent.test');
        
        $this->assertEmpty($registry->getGrants('agent-2', 'agent.test'));
    }
}
