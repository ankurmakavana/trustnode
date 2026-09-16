<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Enums\UserRole;
use App\Services\AgentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AgentHealthApiTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'agent' => [
                'id' => null,
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

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->developer = User::factory()->create([
            'email' => 'dev@trustnode.local',
            'role_id' => $adminRole->id,
        ]);
    }

    public function test_unauthenticated_request_is_rejected()
    {
        $response = $this->getJson('/api/agent/health');
        $response->assertStatus(401);
    }

    public function test_authenticated_request_returns_agent_health_with_all_fields()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');

        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'agent_id',
            'instance_id',
            'status',
            'version',
            'state',
            'started_at',
            'last_heartbeat_at',
            'stopped_at',
            'heartbeat_stale',
            'timestamp'
        ]);

        $this->assertEquals('starting', $response->json('status'));
        $this->assertEquals('starting', $response->json('state'));
    }

    public function test_running_with_fresh_heartbeat_returns_running()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');
        $agent->setState('running');
        $agent->heartbeat();

        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        
        $this->assertEquals('running', $response->json('status'));
        $this->assertFalse($response->json('heartbeat_stale'));
    }

    public function test_running_with_stale_heartbeat_returns_unhealthy()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');
        $agent->setState('running');
        $agent->heartbeat();

        Carbon::setTestNow(now()->addSeconds(config('agent.heartbeat.timeout') + 1));

        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        
        $this->assertEquals('unhealthy', $response->json('status'));
        $this->assertTrue($response->json('heartbeat_stale'));
    }

    public function test_api_does_not_mutate_agent_lifecycle_state_or_identity()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');
        $agent->setState('running');
        $agent->heartbeat();
        
        $initialState = $agent->getState();
        $initialAgentId = $agent->getAgentId();
        $initialInstanceId = $agent->getInstanceId();
        $initialHeartbeat = $agent->getLastHeartbeatAt();

        Carbon::setTestNow(now()->addSeconds(5));

        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        $response->assertStatus(200);

        $this->assertEquals($initialState, $agent->getState());
        $this->assertEquals($initialAgentId, $agent->getAgentId());
        $this->assertEquals($initialInstanceId, $agent->getInstanceId());
        $this->assertEquals($initialHeartbeat, $agent->getLastHeartbeatAt());
    }
    
    public function test_api_does_not_create_heartbeat()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');
        $agent->setState('running');
        Cache::forget('trustnode_agent_heartbeat');
        
        $this->actingAs($this->developer)->getJson('/api/agent/health');
        
        $this->assertNull(Cache::get('trustnode_agent_heartbeat'));
    }

    public function test_stopping_state_returns_stopping()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');
        $agent->setState('running');
        $agent->setState('stopping');
        
        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        $this->assertEquals('stopping', $response->json('status'));
    }

    public function test_stopped_state_returns_stopped()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('stopped');
        
        $response = $this->actingAs($this->developer)->getJson('/api/agent/health');
        $this->assertEquals('stopped', $response->json('status'));
    }
}
