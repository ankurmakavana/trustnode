<?php

namespace Tests\Feature;

use App\Contracts\AgentQueueInterface;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\QueueFullException;
use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentScheduledTasks;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class AgentScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    private const AGENT_ID = 'test-agent';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        DB::table('agent_states')->insert([
            'agent_id' => self::AGENT_ID,
            'state' => json_encode(['state' => 'running']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function service(): AgentScheduledTasks
    {
        return app(AgentScheduledTasks::class);
    }

    private function administrator(array $attributes = []): User
    {
        $role = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        return User::factory()->create(array_merge(['role_id' => $role->id], $attributes));
    }

    private function attachToDefaultTeam(User $user): void
    {
        $org = Organization::firstOrCreate(['slug' => 'default-organization'], ['name' => 'Default Organization']);
        $team = Team::firstOrCreate(
            ['slug' => 'default-team', 'organization_id' => $org->id],
            ['name' => 'Default Team']
        );

        $user->teams()->attach($team->id);
    }

    public function test_nothing_is_enqueued_when_no_administrator_exists()
    {
        $result = $this->service()->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']);

        $this->assertNull($result);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_administrator_without_a_team_is_not_used_as_owner()
    {
        $this->administrator();

        $result = $this->service()->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']);

        $this->assertNull($result);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_suspended_administrator_is_not_used_as_owner()
    {
        $admin = $this->administrator(['status' => UserStatus::SUSPENDED]);
        $this->attachToDefaultTeam($admin);

        $this->assertNull($this->service()->localAdministratorId());
        $this->assertNull($this->service()->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']));
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_scheduled_task_is_owned_by_the_local_administrator()
    {
        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);

        $taskId = $this->service()->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']);

        $this->assertNotNull($taskId);
        $task = DB::table('agent_tasks')->where('id', $taskId)->first();
        $this->assertEquals($admin->id, $task->user_id);
        $this->assertEquals(self::AGENT_ID, $task->agent_id);
        $this->assertEquals('agent.observe_env', $task->type);
    }

    public function test_owner_is_the_lowest_id_active_administrator_with_a_team()
    {
        // A lower-id user without the administrator role must not be picked.
        $viewerRole = Role::where('slug', '!=', UserRole::ADMINISTRATOR->value)->first();
        $viewer = User::factory()->create(['role_id' => $viewerRole->id]);
        $this->attachToDefaultTeam($viewer);

        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);

        $this->assertEquals($admin->id, $this->service()->localAdministratorId());
    }

    public function test_tenant_context_is_cleared_after_a_successful_enqueue()
    {
        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);

        $this->service()->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']);

        $this->assertNull(TenantContext::currentUserId());
    }

    public function test_scheduled_observe_env_run_enqueues_one_task_owned_by_the_administrator()
    {
        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);

        $this->artisan('schedule:test', ['--name' => 'agent.observe_env'])->assertSuccessful();
        // A second run must not duplicate the pending task.
        $this->artisan('schedule:test', ['--name' => 'agent.observe_env'])->assertSuccessful();

        $tasks = DB::table('agent_tasks')->where('type', 'agent.observe_env')->get();
        $this->assertCount(1, $tasks);
        $this->assertEquals($admin->id, $tasks->first()->user_id);
    }

    public function test_scheduled_observe_env_run_enqueues_nothing_without_an_owner()
    {
        $this->artisan('schedule:test', ['--name' => 'agent.observe_env'])->assertSuccessful();

        $this->assertDatabaseCount('agent_tasks', 0);
    }

    private function eligibleProject(array $overrides = []): CodebaseProject
    {
        $org = Organization::firstOrCreate(['slug' => 'default-organization'], ['name' => 'Default Organization']);

        return CodebaseProject::factory()->create(array_merge([
            'organization_id' => $org->id,
            'created_by' => $this->administrator()->id,
            'path' => 'my-project',
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE,
            'continuous_scan_enabled' => true,
        ], $overrides));
    }

    private function projectScanTasks(): \Illuminate\Support\Collection
    {
        return DB::table('agent_tasks')->where('type', 'agent.capability.execute')->get()
            ->map(fn ($row) => ['row' => $row, 'payload' => json_decode($row->payload, true)]);
    }

    public function test_eligible_project_gets_one_project_scoped_task_owned_by_the_administrator()
    {
        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);
        $project = $this->eligibleProject(['created_by' => $admin->id]);

        $queued = $this->service()->enqueueProjectScans(self::AGENT_ID);

        $this->assertSame([$project->id], $queued);
        $tasks = $this->projectScanTasks();
        $this->assertCount(1, $tasks);
        $this->assertEquals($admin->id, $tasks->first()['row']->user_id);
        $this->assertSame($project->id, $tasks->first()['payload']['arguments']['project_id']);
        $this->assertArrayNotHasKey('target_path', $tasks->first()['payload']['arguments']);
    }

    public function test_inactive_project_is_not_scanned()
    {
        $this->attachToDefaultTeam($this->administrator());
        $this->eligibleProject(['status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ARCHIVED]);

        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertCount(0, $this->projectScanTasks());
    }

    public function test_project_without_continuous_scanning_is_not_scanned()
    {
        $this->attachToDefaultTeam($this->administrator());
        $this->eligibleProject(['continuous_scan_enabled' => false]);

        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertCount(0, $this->projectScanTasks());
    }

    public function test_soft_deleted_project_is_not_scanned()
    {
        $this->attachToDefaultTeam($this->administrator());
        $this->eligibleProject()->delete();

        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertCount(0, $this->projectScanTasks());
    }

    public function test_project_in_another_organization_is_not_scanned()
    {
        $this->attachToDefaultTeam($this->administrator());
        $otherOrg = Organization::factory()->create();
        $this->eligibleProject(['organization_id' => $otherOrg->id]);

        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertCount(0, $this->projectScanTasks());
    }

    public function test_no_project_scans_are_queued_without_an_owner()
    {
        // Eligible project exists, but no administrator with a team does.
        $this->eligibleProject();

        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertCount(0, $this->projectScanTasks());
    }

    public function test_pending_project_scan_is_not_duplicated()
    {
        $this->attachToDefaultTeam($this->administrator());
        $project = $this->eligibleProject();

        $this->assertSame([$project->id], $this->service()->enqueueProjectScans(self::AGENT_ID));
        $this->assertSame([], $this->service()->enqueueProjectScans(self::AGENT_ID));

        $this->assertCount(1, $this->projectScanTasks());
    }

    public function test_each_eligible_project_gets_its_own_task()
    {
        $this->attachToDefaultTeam($this->administrator());
        $first = $this->eligibleProject(['path' => 'first']);
        $second = $this->eligibleProject(['path' => 'second']);

        $queued = $this->service()->enqueueProjectScans(self::AGENT_ID);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $queued);
        $ids = $this->projectScanTasks()->map(fn ($t) => $t['payload']['arguments']['project_id'])->sort()->values()->all();
        $this->assertSame(collect([$first->id, $second->id])->sort()->values()->all(), $ids);
    }

    public function test_scheduled_run_never_scans_the_whole_mount()
    {
        $this->attachToDefaultTeam($this->administrator());
        $this->eligibleProject();

        $mount = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_mount_' . uniqid();
        mkdir($mount);
        config(['agent.scan_mount_dir' => $mount]);

        try {
            $this->artisan('schedule:test', ['--name' => 'agent.continuous_scan'])->assertSuccessful();
        } finally {
            rmdir($mount);
        }

        $tasks = $this->projectScanTasks();
        $this->assertCount(1, $tasks);
        foreach ($tasks as $task) {
            $this->assertArrayHasKey('project_id', $task['payload']['arguments']);
            $this->assertArrayNotHasKey('target_path', $task['payload']['arguments']);
        }
    }

    public function test_tenant_context_is_cleared_when_enqueue_fails()
    {
        $admin = $this->administrator();
        $this->attachToDefaultTeam($admin);

        $queue = Mockery::mock(AgentQueueInterface::class);
        $queue->shouldReceive('enqueue')->once()->andThrow(new QueueFullException('full'));

        $this->expectException(QueueFullException::class);

        try {
            (new AgentScheduledTasks($queue))->enqueueAsLocalAdministrator(self::AGENT_ID, 'agent.observe_env', ['target' => 'x']);
        } finally {
            $this->assertNull(TenantContext::currentUserId());
        }
    }
}
