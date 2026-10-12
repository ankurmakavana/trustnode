<?php

namespace Tests\Unit;

use App\Services\AgentService;
use App\Providers\AgentServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentServiceTakeoverTest extends TestCase
{
    // start() notifies administrators, so the users and roles tables must exist.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent' => [
                'id' => 'test-takeover-agent',
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

    protected function tearDown(): void
    {
        Cache::forget('trustnode_agent_state_test-takeover-agent');
        Cache::forget('trustnode_agent_lock_test-takeover-agent');
        Cache::forget('trustnode_agent_persist_lock_test-takeover-agent');
        Cache::forget('trustnode_agent_heartbeat_test-takeover-agent');
        parent::tearDown();
    }

    public function test_startup_aborts_when_another_agent_runtime_has_fresh_heartbeat()
    {
        $agentA = new AgentService();
        $agentA->start(); // Starts normally

        $agentB = new AgentService();
        $agentB->start(); // Should abort

        $this->assertTrue($agentA->isRunning());
        $this->assertFalse($agentB->isRunning());
        $this->assertEquals('stopped', $agentB->getState());
    }

    public function test_startup_takes_over_when_existing_runtime_heartbeat_is_stale()
    {
        $agentA = new AgentService();
        $agentA->start();

        // Make heartbeat stale
        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(61));

        $agentB = new AgentService();
        $agentB->start(); // Should take over

        $this->assertTrue($agentB->isRunning());
        $this->assertEquals($agentB->getInstanceId(), $agentB->getHealthStatus()['instance_id']);
    }

    public function test_stale_takeover_persists_the_new_instance_id()
    {
        $agentA = new AgentService();
        $agentA->start();
        $idA = $agentA->getInstanceId();

        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(61));

        $agentB = new AgentService();
        $agentB->start();
        $idB = $agentB->getInstanceId();

        $cachedState = Cache::get('trustnode_agent_state_test-takeover-agent');
        $this->assertEquals($idB, $cachedState['instance_id']);
        $this->assertNotEquals($idA, $cachedState['instance_id']);
    }

    public function test_heartbeat_cache_key_is_scoped_by_agent_id()
    {
        $agent = new AgentService();
        $agent->start();
        $this->assertNotNull(Cache::get('trustnode_agent_heartbeat_test-takeover-agent'));
    }

    public function test_old_usurped_runtime_cannot_overwrite_new_runtime_heartbeat_and_stops_itself()
    {
        $agentA = new AgentService();
        $agentA->start();

        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(61));

        $agentB = new AgentService();
        $agentB->start(); // B takes over

        // A tries to heartbeat
        $agentA->heartbeat();

        // A should detect usurpation and stop itself locally
        $this->assertTrue($agentA->isStopped());
        
        // But A MUST NOT have overwritten B's state in the cache
        $cachedState = Cache::get('trustnode_agent_state_test-takeover-agent');
        $this->assertEquals($agentB->getInstanceId(), $cachedState['instance_id'], 'Stale agent overwrote the active agent state!');
        $this->assertEquals(AgentService::S_RUNNING, $cachedState['state']);
    }

    public function test_duplicate_startup_does_not_create_two_authoritative_runtimes()
    {
        $agentA = new AgentService();
        $agentA->start();
        
        $agentB = new AgentService();
        $agentB->start();

        // Only A should be active, B was rejected
        $this->assertTrue($agentA->isRunning());
        $this->assertTrue($agentB->isStopped());
    }

    public function test_agent_service_provider_does_not_bootstrap_agent_for_normal_console_commands()
    {
        $_SERVER['argv'] = ['artisan', 'queue:work'];
        $app = \Mockery::mock($this->app);
        $app->shouldReceive('runningInConsole')->andReturn(true);
        $app->shouldReceive('environment')->with('testing')->andReturn(false);
        $app->shouldReceive('make')->andReturnUsing(function($class) {
            return $this->app->make($class);
        });
        $app->shouldReceive('singleton')->andReturnNull();
        
        $provider = new AgentServiceProvider($app);
        $provider->boot();
        
        $agent = $this->app->make(AgentService::class);
        $this->assertTrue($agent->isStopped());
    }
    
    public function test_designated_agent_runtime_does_bootstrap_agent()
    {
        $_SERVER['argv'] = ['artisan', 'agent:run'];
        $app = \Mockery::mock($this->app);
        $app->shouldReceive('runningInConsole')->andReturn(true);
        $app->shouldReceive('environment')->with('testing')->andReturn(false);
        $app->shouldReceive('make')->andReturnUsing(function($class) {
            return $this->app->make($class);
        });
        $app->shouldReceive('singleton')->andReturnNull();
        
        $provider = new AgentServiceProvider($app);
        $provider->boot();
        
        $agent = $this->app->make(AgentService::class);
        $this->assertTrue($agent->isRunning());
        $agent->stop();
    }

    public function test_true_concurrency_race_condition_using_db_cas()
    {
        // Real database, no mocks: two runtimes start against the same agent_states row. The
        // compare-and-swap on the stored instance must leave exactly one owner.
        config(['agent.state.driver' => 'database']);

        $agentA = new AgentService();
        $agentB = new AgentService();

        $agentA->start(); // wins the row
        $agentB->start(); // must lose: A holds a fresh heartbeat

        $this->assertTrue($agentA->isRunning());
        $this->assertTrue($agentB->isStopped());

        $stored = json_decode(\Illuminate\Support\Facades\DB::table('agent_states')->value('state'), true);
        $this->assertSame($agentA->getInstanceId(), $stored['instance_id'] ?? null);
    }
}
