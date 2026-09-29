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
            'app.key' => 'base64:3f6B78dZ7x7VbLqP3g3kL5x9Z8w1y2z3A4b5c6d7e8f=',
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

        $org = \App\Models\Organization::create(['name' => 'Test Org', 'slug' => 'test-org']);
        $team = \App\Models\Team::create(['organization_id' => $org->id, 'name' => 'Default Team', 'slug' => 'default-team']);
        $team->users()->attach($this->developer->id);
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
        Cache::forget('trustnode_agent_heartbeat_' . $agent->getAgentId());

        $this->actingAs($this->developer)->getJson('/api/agent/health');

        $this->assertNull(Cache::get('trustnode_agent_heartbeat_' . $agent->getAgentId()));
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

    public function test_console_endpoint_returns_operational_data()
    {
        $agent = $this->app->make(AgentService::class);
        $agent->setState('starting');

        $response = $this->actingAs($this->developer)->getJson('/api/agent/console');
        $response->assertStatus(200);

        $response->assertJsonStructure([
            'overall_state',
            'agent' => ['id', 'version', 'environment', 'instance_id'],
            'process' => ['status', 'last_heartbeat_at', 'started_at'],
            'worker' => ['status'],
            'observation' => ['status', 'target', 'stale'],
            'scan' => ['status', 'scanner'],
            'queue' => ['pending', 'processing', 'completed', 'failed'],
            'metrics' => ['observations', 'scans', 'findings', 'tasks_processed', 'open_findings', 'regressions'],
            'health',
            'recent_scans',
            'recent_tasks',
            'recent_findings',
            'observation_target',
            'activity_timeline',
        ]);
    }

    public function test_agent_observation_creates_scan_record_and_propagates_scan_id()
    {
        config(['agent.state.driver' => 'database']);
        $agentService = $this->app->make(AgentService::class);
        $agentService->start();

        $queue = $this->app->make(\App\Contracts\AgentQueueInterface::class);
        $scanner = $this->app->make(\App\Services\Scan\Scanners\SecretScanner::class);

        $handler = new \App\Handlers\AgentObserveEnvTaskHandler($scanner, $queue, $agentService);

        Cache::flush();
        $envPath = base_path('.env');
        $originalEnvContent = file_exists($envPath) ? file_get_contents($envPath) : '';
        try {
            file_put_contents($envPath, "APP_KEY=base64:3f6B78dZ7x7VbLqP3g3kL5x9Z8w1y2z3A4b5c6d7e8f=\nAWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE\n");

            $handler->handle([
                'id' => 'task-obs-test-1',
                'agent_id' => $agentService->getAgentId(),
                'type' => 'agent.observe_env',
                'payload' => []
            ]);
        } finally {
            if ($originalEnvContent !== '') {
                file_put_contents($envPath, $originalEnvContent);
            }
        }

        $scan = \App\Models\Scan::withoutGlobalScopes()->where('target', basename($envPath))->latest()->first();
        $this->assertNotNull($scan);
        $this->assertEquals(\App\Enums\Scan\ScanStatus::COMPLETED, $scan->status);

        $task = $queue->dequeue($agentService->getAgentId());
        $this->assertNotNull($task);
        $this->assertEquals('agent.report_finding', $task['type']);
        $this->assertEquals($scan->id, $task['payload']['scan_id']);

        $reportHandler = $this->app->make(\App\Handlers\AgentReportFindingTaskHandler::class);
        $reportHandler->handle($task);

        $finding = \App\Models\Finding::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertEquals($scan->id, $finding->scan_id);
        $this->assertEquals($agentService->getAgentId(), $finding->agent_id);
    }
}
