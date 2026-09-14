<?php

namespace Tests\Unit;

use App\Services\AgentService;
use App\Providers\AgentServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AgentServiceBootstrapTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('agent', [
            'id' => null,
            'version' => '1.0.0',
            'state' => [
                'driver' => 'cache',
                'table' => 'agent_states',
            ],
            'heartbeat' => [
                'timeout' => 60,
            ],
        ]);
    }

    /**
     * A. AgentService is registered/resolvable from the application container.
     */
    public function test_agent_service_is_resolvable_from_container()
    {
        $agent = $this->app->make(AgentService::class);

        $this->assertInstanceOf(AgentService::class, $agent);
    }

    /**
     * B. Resolving AgentService twice in the same application runtime returns the same service instance.
     */
    public function test_resolving_agent_service_twice_returns_same_instance()
    {
        $agent1 = $this->app->make(AgentService::class);
        $agent2 = $this->app->make(AgentService::class);

        $this->assertSame($agent1, $agent2);
    }

    /**
     * C. The same instance_id is retained across container resolutions.
     */
    public function test_instance_id_retained_across_resolutions()
    {
        $agent1 = $this->app->make(AgentService::class);
        $agent2 = $this->app->make(AgentService::class);

        $this->assertEquals($agent1->getInstanceId(), $agent2->getInstanceId());
    }

    /**
     * D. Agent startup results in running state.
     */
    public function test_startup_results_in_running_state()
    {
        $agent = $this->app->make(AgentService::class);

        $this->assertFalse($agent->isRunning());

        $agent->start();

        $this->assertTrue($agent->isRunning());
        $this->assertEquals('running', $agent->getState());
    }

    /**
     * E. Startup establishes heartbeat.
     */
    public function test_startup_establishes_heartbeat()
    {
        $agent = $this->app->make(AgentService::class);

        $agent->start();

        $this->assertNotNull($agent->getLastHeartbeatAt());
        $this->assertNotNull(Cache::get('trustnode_agent_heartbeat'));
    }

    /**
     * G. Startup does not change agent_id.
     */
    public function test_startup_does_not_change_agent_id()
    {
        $agent = $this->app->make(AgentService::class);

        $initialAgentId = $agent->getAgentId();

        $agent->start();

        $this->assertEquals($initialAgentId, $agent->getAgentId());
    }

    /**
     * F. Startup does not change instance_id.
     */
    public function test_startup_does_not_change_instance_id()
    {
        $agent = $this->app->make(AgentService::class);

        $initialInstanceId = $agent->getInstanceId();

        $agent->start();

        $this->assertEquals($initialInstanceId, $agent->getInstanceId());
    }

    /**
     * H. Repeated start while already running preserves existing behavior.
     */
    public function test_repeated_start_while_running_preserves_behavior()
    {
        $agent = $this->app->make(AgentService::class);

        $agent->start();
        $stateAfterFirstStart = $agent->getState();
        $instanceIdAfterFirstStart = $agent->getInstanceId();

        $agent->start();

        $this->assertEquals($stateAfterFirstStart, $agent->getState());
        $this->assertEquals($instanceIdAfterFirstStart, $agent->getInstanceId());
    }

    /**
     * I. Startup does not accidentally create multiple in-memory AgentService instances within the same container runtime.
     */
    public function test_no_multiple_in_memory_instances_within_container_runtime()
    {
        $agent1 = $this->app->make(AgentService::class);
        $agent2 = $this->app->make(AgentService::class);
        $agent3 = $this->app->make(AgentService::class);

        $this->assertSame($agent1, $agent2);
        $this->assertSame($agent2, $agent3);
        $this->assertSame($agent1, $agent3);
    }

    /**
     * Verify AgentServiceProvider is registered in the application.
     */
    public function test_agent_service_provider_is_registered()
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(AgentServiceProvider::class, $providers);
    }
}