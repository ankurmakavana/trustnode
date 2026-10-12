<?php

namespace Tests\Feature;

use App\Contracts\AgentCapabilityRegistryInterface;
use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Models\CodebaseProject;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Team;
use App\Models\User;
use App\Services\AgentService;
use App\Services\AgentWorker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Core developer workflow in isolation: an explicitly registered project is scanned through the API,
 * the real worker executes the queued task, and the scan and its findings are persisted. Uses a
 * temporary mount and in-memory SQLite. No live database, container or repository is touched.
 */
class ProjectScanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $mount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->mount = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_workflow_' . uniqid();
        mkdir($this->mount . DIRECTORY_SEPARATOR . 'approved-app', 0777, true);
        // A deliberately insecure line so the SAST rules have something to find. No real credential.
        // Untrusted input flows into eval(). The scanner tracks variables assigned from request input,
        // so the source is assigned to a variable first. This is a synthetic fixture.
        file_put_contents(
            $this->mount . DIRECTORY_SEPARATOR . 'approved-app' . DIRECTORY_SEPARATOR . 'index.php',
            "<?php\n\$code = \$_GET['code'];\neval(\$code);\n"
        );
        config(['agent.scan_mount_dir' => $this->mount]);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->mount);
        parent::tearDown();
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_registered_project_is_scanned_through_the_worker_and_findings_are_persisted()
    {
        $adminRole = Role::where('slug', 'administrator')->firstOrFail();
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $org->id])->users()->attach($admin->id);

        // The project is registered explicitly, inside the mount, under the admin's organization.
        $project = CodebaseProject::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'path' => 'approved-app',
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        $agent = $this->app->make(AgentService::class);
        $agent->start();
        $agentId = $agent->getAgentId();

        DB::table('agent_states')->updateOrInsert(
            ['agent_id' => $agentId],
            ['state' => json_encode(['state' => 'running']), 'created_at' => now(), 'updated_at' => now()]
        );

        $registry = $this->app->make(AgentCapabilityRegistryInterface::class);
        $registry->addGrant($agentId, 'agent:scan_codebase');
        $registry->addGrant($agentId, 'agent:report');
        $registry->addGrant($agentId, 'agent:observe');

        // 1. Scan request through the API.
        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/scan")
            ->assertOk()
            ->assertJsonPath('message', 'Scan queued successfully.');

        // 2. The real worker drains the queue (capability task, then report-finding tasks).
        $worker = $this->app->make(AgentWorker::class);
        for ($i = 0; $i < 20; $i++) {
            if (!$worker->runOnce()) {
                break;
            }
        }

        // 3. The scan and its findings are persisted and linked to the project's organization.
        $scan = Scan::withoutGlobalScopes()->where('target', realpath($this->mount . DIRECTORY_SEPARATOR . 'approved-app'))->first();
        $this->assertNotNull($scan, 'The worker did not create a scan for the approved project.');
        $this->assertSame('completed', $scan->status->value ?? $scan->status);

        $findings = Finding::withoutGlobalScopes()->where('scan_id', $scan->id)->get();
        $this->assertGreaterThan(0, $findings->count(), 'No findings were persisted for the approved project.');

        // 4. The findings are visible to the owner through the API (tenant-safe read path).
        $this->actingAs($admin)->getJson("/api/findings/{$findings->first()->id}")->assertOk();
    }
}
