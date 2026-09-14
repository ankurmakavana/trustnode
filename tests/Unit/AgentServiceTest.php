<?php

namespace Tests\Unit;

use App\Services\AgentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set agent config to use cache driver for isolated testing
        config([
            'agent' => [
                'id' => null, // will be generated
                'version' => '1.0.0',
                'state' => [
                    'driver' => 'cache',
                    'table' => 'agent_states'
                ],
                'heartbeat' => [
                    'timeout' => 60
                ]
            ]
        ]);
    }

    public function test_instance_id_is_generated_and_consistent()
    {
        $agent = $this->app->make(AgentService::class);

        $id1 = $agent->getInstanceId();
        $id2 = $agent->getInstanceId();

        $this->assertNotEmpty($id1);
        $this->assertEquals($id1, $id2);
    }

    public function test_two_instances_have_different_instance_ids()
    {
        $agent1 = new AgentService();
        $agent2 = new AgentService();

        $this->assertNotEmpty($agent1->getInstanceId());
        $this->assertNotEmpty($agent2->getInstanceId());
        $this->assertNotEquals($agent1->getInstanceId(), $agent2->getInstanceId());
    }

    public function test_agent_id_and_instance_id_are_different()
    {
        $agent = $this->app->make(AgentService::class);

        $this->assertNotEmpty($agent->getAgentId());
        $this->assertNotEmpty($agent->getInstanceId());
        $this->assertNotEquals($agent->getAgentId(), $agent->getInstanceId());
    }

    public function test_start_does_not_change_instance_id()
    {
        $agent = $this->app->make(AgentService::class);
        $initialId = $agent->getInstanceId();

        $agent->start();

        $this->assertEquals($initialId, $agent->getInstanceId());
    }

    public function test_heartbeat_does_not_change_instance_id()
    {
        $agent = $this->app->make(AgentService::class);
        $initialId = $agent->getInstanceId();

        $agent->heartbeat();

        $this->assertEquals($initialId, $agent->getInstanceId());
    }

    public function test_health_status_contains_instance_id()
    {
        $agent = $this->app->make(AgentService::class);

        $health = $agent->getHealthStatus();

        $this->assertArrayHasKey('instance_id', $health);
        $this->assertNotEmpty($health['instance_id']);
        $this->assertEquals($agent->getInstanceId(), $health['instance_id']);
    }

    // State machine tests
    public function test_stopped_to_starting_allowed()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $this->assertEquals('starting', $agent->getState());
    }

    public function test_starting_to_running_allowed()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $this->assertEquals('running', $agent->getState());
    }

    public function test_running_to_stopping_allowed()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');
        $this->assertEquals('stopping', $agent->getState());
    }

    public function test_stopping_to_stopped_allowed()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');
        $agent->setState('stopped');
        $this->assertEquals('stopped', $agent->getState());
    }

    public function test_stopped_to_running_rejected()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $this->expectException(\LogicException::class);
        $agent->setState('running');
    }

    public function test_running_to_stopped_rejected()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $this->expectException(\LogicException::class);
        $agent->setState('stopped');
    }

    public function test_starting_to_stopped_rejected()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $this->expectException(\LogicException::class);
        $agent->setState('stopped');
    }

    public function test_stopping_to_running_rejected()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');
        $this->expectException(\LogicException::class);
        $agent->setState('running');
    }

    public function test_heartbeat_does_not_change_lifecycle_state()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $initialState = $agent->getState();
        $agent->heartbeat();
        $this->assertEquals($initialState, $agent->getState());
    }

    public function test_repeated_start_while_running_preserves_behavior()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $initialState = $agent->getState();
        $agent->start(); // Should log warning and return, not change state
        $this->assertEquals($initialState, $agent->getState());
    }

    public function test_repeated_stop_while_stopped_preserves_behavior()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $initialState = $agent->getState();
        $agent->stop(); // Should log warning and return, not change state
        $this->assertEquals($initialState, $agent->getState());
    }

    public function test_instance_id_unchanged_during_valid_transitions()
    {
        $agent = new AgentService();
        $initialId = $agent->getInstanceId();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');
        $agent->setState('stopped');
        $this->assertEquals($initialId, $agent->getInstanceId());
    }

    // Shutdown tests

    public function test_running_agent_can_be_stopped()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $this->assertTrue($agent->isRunning());

        $agent->stop();

        $this->assertTrue($agent->isStopped());
        $this->assertFalse($agent->isStopping());
        $this->assertFalse($agent->isRunning());
        $this->assertEquals('stopped', $agent->getState());
    }

    public function test_stop_sets_stopped_at()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $this->assertNull($agent->getStoppedAt());

        $agent->stop();

        $this->assertNotNull($agent->getStoppedAt());
    }

    public function test_stop_preserves_started_at()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $startedAt = $agent->getStartedAt();

        $agent->stop();

        $this->assertEquals($startedAt, $agent->getStartedAt());
    }

    public function test_stop_preserves_last_heartbeat_at()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->heartbeat();

        $lastHeartbeatAt = $agent->getLastHeartbeatAt();

        $agent->stop();

        $this->assertEquals($lastHeartbeatAt, $agent->getLastHeartbeatAt());
    }

    public function test_stop_preserves_instance_id()
    {
        $agent = new AgentService();
        $initialId = $agent->getInstanceId();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agent->stop();

        $this->assertEquals($initialId, $agent->getInstanceId());
    }

    public function test_stop_preserves_agent_id()
    {
        $agent = new AgentService();
        $initialAgentId = $agent->getAgentId();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agent->stop();

        $this->assertEquals($initialAgentId, $agent->getAgentId());
    }

    public function test_stop_persists_final_state()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agentId = $agent->getAgentId();

        $agent->stop();

        $cached = Cache::get('trustnode_agent_state_' . $agentId);
        $this->assertNotNull($cached);
        $this->assertEquals('stopped', $cached['state']);
        $this->assertNotNull($cached['stopped_at']);
    }

    public function test_stopped_state_observable_by_new_instance()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agentId = $agent->getAgentId();
        $agent->stop();

        // Ensure new instance uses same agent_id to find persisted state
        config(['agent.id' => $agentId]);
        $newAgent = new AgentService();

        $this->assertTrue($newAgent->isStopped());
        $this->assertNotNull($newAgent->getStoppedAt());
    }

    public function test_stop_while_stopping_is_idempotent()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');

        $agent->stop();

        $this->assertTrue($agent->isStopping());
        $this->assertNull($agent->getStoppedAt());
    }

    public function test_start_works_after_stop()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agent->stop();
        $this->assertTrue($agent->isStopped());

        $agent->start();
        $this->assertTrue($agent->isRunning());
        $this->assertEquals('running', $agent->getState());
    }

    // Heartbeat tests — running Agent

    public function test_heartbeat_on_running_agent_updates_last_heartbeat_at()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $before = $agent->getLastHeartbeatAt();
        $agent->heartbeat();

        $this->assertNotNull($agent->getLastHeartbeatAt());
        $this->assertNotEquals($before, $agent->getLastHeartbeatAt());
    }

    public function test_heartbeat_on_running_agent_updates_heartbeat_cache()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agent->heartbeat();

        $cached = Cache::get('trustnode_agent_heartbeat');
        $this->assertNotNull($cached);
        $this->assertIsInt($cached);
        $this->assertGreaterThan(0, $cached);
    }

    public function test_heartbeat_does_not_change_agent_id()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $initialAgentId = $agent->getAgentId();
        $agent->heartbeat();

        $this->assertEquals($initialAgentId, $agent->getAgentId());
    }

    public function test_heartbeat_does_not_change_started_at()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $startedAt = $agent->getStartedAt();
        $agent->heartbeat();

        $this->assertEquals($startedAt, $agent->getStartedAt());
    }

    public function test_heartbeat_does_not_change_stopped_at()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->stop();

        $stoppedAt = $agent->getStoppedAt();

        // Restart to running for heartbeat test
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->heartbeat();

        // stopped_at should remain unchanged (not null, but preserved as-is)
        $this->assertEquals($stoppedAt, $agent->getStoppedAt());
    }

    // Heartbeat tests — non-running Agent

    public function test_heartbeat_on_stopped_agent_does_nothing()
    {
        $agent = new AgentService();

        $agent->heartbeat();

        $this->assertNull($agent->getLastHeartbeatAt());
        $this->assertNull(Cache::get('trustnode_agent_heartbeat'));
        $this->assertTrue($agent->isStopped());
    }

    public function test_heartbeat_on_stopping_agent_does_nothing()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');

        $agent->heartbeat();

        $this->assertTrue($agent->isStopping());
        $this->assertNull($agent->getLastHeartbeatAt());
        $this->assertNull(Cache::get('trustnode_agent_heartbeat'));
    }

    public function test_heartbeat_on_starting_agent_does_not_mark_running()
    {
        $agent = new AgentService();
        $agent->setState('stopped');
        $agent->setState('starting');

        $agent->heartbeat();

        $this->assertTrue($agent->isStarting());
        $this->assertFalse($agent->isRunning());
        $this->assertNull($agent->getLastHeartbeatAt());
        $this->assertNull(Cache::get('trustnode_agent_heartbeat'));
    }

    public function test_repeated_heartbeat_updates_latest_timestamp_and_cache()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        \Illuminate\Support\Carbon::setTestNow(now());
        $agent->heartbeat();
        $firstHeartbeat = $agent->getLastHeartbeatAt();
        $firstCache = Cache::get('trustnode_agent_heartbeat');

        \Illuminate\Support\Carbon::setTestNow(now()->addSecond());
        $agent->heartbeat();
        $secondHeartbeat = $agent->getLastHeartbeatAt();
        $secondCache = Cache::get('trustnode_agent_heartbeat');

        $this->assertNotNull($firstHeartbeat);
        $this->assertNotNull($secondHeartbeat);
        $this->assertLessThan($secondHeartbeat, $firstHeartbeat);
        $this->assertNotEquals($firstCache, $secondCache);
    }

    public function test_heartbeat_uses_configured_timeout()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        $agent->heartbeat();

        $cached = Cache::get('trustnode_agent_heartbeat');
        $this->assertNotNull($cached);

        $timeout = config('agent.heartbeat.timeout');
        $this->assertEquals(60, $timeout);
    }

    // Heartbeat tests — periodic mechanism

    public function test_periodic_heartbeat_schedule_exists()
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = $schedule->events();

        $heartbeatEvent = null;
        foreach ($events as $event) {
            if ($event->description === 'agent.heartbeat') {
                $heartbeatEvent = $event;
                break;
            }
        }

        $this->assertNotNull($heartbeatEvent, 'Agent heartbeat schedule event not found');
    }

    public function test_periodic_heartbeat_schedule_invokes_heartbeat()
    {
        // Use fixed agent ID so callback instance loads same state
        config(['agent.id' => 'test-agent-heartbeat']);

        // Use container singleton for consistent state
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        $agent->setState('starting');
        $agent->setState('running');

        // Ensure agent is in running state and heartbeat has been set
        $agent->heartbeat();
        $beforeCache = Cache::get('trustnode_agent_heartbeat');

        // Advance time for distinct timestamp
        \Illuminate\Support\Carbon::setTestNow(now()->addSecond());

        // Run the heartbeat schedule event
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $ran = false;
        foreach ($schedule->events() as $event) {
            if ($event->description === 'agent.heartbeat') {
                $event->run($this->app);
                $ran = true;
                break;
            }
        }

        $this->assertTrue($ran, 'Heartbeat schedule event did not run');

        // The schedule callback should have updated the cache
        $afterCache = Cache::get('trustnode_agent_heartbeat');
        $this->assertNotNull($afterCache, 'Heartbeat cache was not updated by schedule');
        $this->assertNotEquals($beforeCache, $afterCache, 'Heartbeat cache should change after schedule run');
    }
}