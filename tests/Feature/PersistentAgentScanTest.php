<?php

namespace Tests\Feature;

use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Team;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\CodebaseProject\CodebaseProjectStatus;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersistentAgentScanTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->developer = User::factory()->create([
            'email' => 'agent-tester@trustnode.local',
            'role_id' => $adminRole->id,
        ]);

        $this->organization = Organization::create([
            'name' => 'Agent Security Org',
            'slug' => 'agent-security-org',
        ]);

        $team = Team::create([
            'organization_id' => $this->organization->id,
            'name' => 'Agent Team',
            'slug' => 'agent-team',
        ]);

        $team->users()->attach($this->developer->id);
    }

    /**
     * Test 1: Agent heartbeat registration and online listing
     */
    public function test_agent_heartbeat_and_listing_workflow(): void
    {
        $agentId = 'local-agent-mac-01';

        // Post heartbeat
        $response = $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Darwin',
            'hostname' => 'developer-macbook',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'acknowledged',
                'agent_id' => $agentId,
            ]);

        // Verify listed as online
        $listResponse = $this->actingAs($this->developer)->getJson('/api/agent/list');
        $listResponse->assertOk()
            ->assertJsonFragment([
                'agent_id' => $agentId,
                'status' => 'online',
                'os' => 'Darwin',
            ]);
    }

    /**
     * Test 2: Project registration with agent source_type and cross-platform path
     */
    public function test_register_persistent_agent_project(): void
    {
        $agentId = 'local-agent-win-02';

        // Register agent heartbeat first to satisfy online verification
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Windows',
            'hostname' => 'win-dev-box',
        ])->assertOk();

        $payload = [
            'name' => 'Cross-Platform Project',
            'path' => 'C:\\projects\\my-app',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ];

        $response = $this->actingAs($this->developer)->postJson('/api/projects', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cross-Platform Project')
            ->assertJsonPath('data.source_type', 'agent')
            ->assertJsonPath('data.agent_id', $agentId);

        $this->assertDatabaseHas('codebase_projects', [
            'name' => 'Cross-Platform Project',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'status' => 'active',
        ]);
    }

    /**
     * Test 3: Path traversal sequences are strictly rejected for agent projects
     */
    public function test_agent_project_rejects_traversal_paths(): void
    {
        $response = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Traversal Attack',
            'path' => '/var/www/../../etc/passwd',
            'source_type' => 'agent',
            'agent_id' => 'agent-01',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Path traversal sequences are not permitted.']);
    }

    /**
     * Test 3b: System root and drive root are strictly rejected
     */
    public function test_agent_project_rejects_system_root_paths(): void
    {
        $agentId = 'agent-root-test';
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Windows',
        ])->assertOk();

        $response = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Root Drive Attack',
            'path' => 'C:',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Authorizing system root, entire drives, or critical OS directories is not permitted.']);
    }

    /**
     * Test 3c: Automatic Agent Selection when agent_id is omitted
     */
    public function test_automatic_agent_selection(): void
    {
        $agentId = 'auto-agent-01';
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Linux',
        ])->assertOk();

        $response = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Auto Selected Project',
            'path' => '/var/projects/auto-app',
            'source_type' => 'agent',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Auto Selected Project')
            ->assertJsonPath('data.agent_id', $agentId);
    }

    /**
     * Test 3d: Rejection when agent is offline or invalid
     */
    public function test_agent_project_rejects_offline_agent(): void
    {
        $response = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Offline Agent Project',
            'path' => '/var/projects/offline-app',
            'source_type' => 'agent',
            'agent_id' => 'completely-offline-agent',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'The selected Agent (completely-offline-agent) is offline or unverified. Please ensure it is running.']);
    }

    /**
     * Test 4: Revocation immediately sets status to archived and soft-deletes
     */
    public function test_project_revocation_prevents_subsequent_scans(): void
    {
        $agentId = 'agent-linux-03';

        $project = CodebaseProject::create([
            'name' => 'Revocation Target',
            'path' => '/home/dev/projects/secure-app',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
            'organization_id' => $this->organization->id,
            'created_by' => $this->developer->id,
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        // Revoke project
        $deleteResponse = $this->actingAs($this->developer)->deleteJson("/api/projects/{$project->id}");
        $deleteResponse->assertOk()
            ->assertJson(['message' => 'Project archived and access revoked.']);

        $project->refresh();
        $this->assertEquals(CodebaseProjectStatus::ARCHIVED, $project->status);
        $this->assertNotNull($project->deleted_at);

        // Attempting to trigger scan on revoked project must fail
        $scanResponse = $this->actingAs($this->developer)->postJson("/api/projects/{$project->id}/scan");
        $scanResponse->assertNotFound();

        // Attempting to upload scan for revoked project must fail
        $zipPath = tempnam(sys_get_temp_dir(), 'test_zip_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('test.txt', 'hello');
        $zip->close();

        $uploadResponse = $this->actingAs($this->developer)->postJson("/api/scans/local", [
            'archive' => new \Illuminate\Http\UploadedFile($zipPath, 'test.zip', 'application/zip', null, true),
            'target' => 'Revocation Target',
            'project_id' => $project->id,
        ]);
        @unlink($zipPath);

        $uploadResponse->assertStatus(422)
            ->assertJson(['message' => 'Project authorization has been revoked or is inactive.']);
    }

    /**
     * Test 5: Synthetic end-to-end agent scan execution and findings persistence
     */
    public function test_synthetic_end_to_end_agent_scan(): void
    {
        $agentId = 'agent-synthetic-e2e';

        // 1. Agent sends heartbeat
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => PHP_OS_FAMILY,
            'hostname' => 'synthetic-host',
        ])->assertOk();

        // 2. Authorize project
        $createRes = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Synthetic App',
            'path' => '/tmp/synthetic-app',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);
        $createRes->assertCreated();
        $projectId = $createRes->json('data.id');

        // 3. Create synthetic code archive containing a simulated secret
        $zipPath = tempnam(sys_get_temp_dir(), 'synth_zip_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        // Add synthetic file with simulated AWS access key pattern
        $syntheticContent = '<?php $aws_key = "AKIA11111111EXAMPLE1";';
        $zip->addFromString('config.php', $syntheticContent);
        $zip->close();

        // 4. Ingest scan via storeLocal
        $uploadRes = $this->actingAs($this->developer)->postJson('/api/scans/local', [
            'archive' => new \Illuminate\Http\UploadedFile($zipPath, 'synth.zip', 'application/zip', null, true),
            'target' => 'Synthetic App',
            'project_id' => $projectId,
        ]);
        @unlink($zipPath);

        $uploadRes->assertCreated();
        $scanId = $uploadRes->json('id');
        $this->assertNotNull($scanId);

        $scan = Scan::find($scanId);
        $this->assertEquals(\App\Enums\Scan\ScanStatus::QUEUED, $scan->status);

        // 5. Execute ScanLocalJob
        $job = new \App\Jobs\ScanLocalJob($scan, "local-scans/scan-{$scanId}.zip");
        $job->handle(
            app(\App\Services\Scan\RepositoryScanner::class),
            app(\App\Services\Import\FingerprintService::class)
        );

        // 6. Verify scan completed and findings recorded
        $scan->refresh();
        $this->assertEquals(\App\Enums\Scan\ScanStatus::COMPLETED, $scan->status);
        $this->assertEquals(100, $scan->progress);

        $findings = \App\Models\Finding::where('scan_id', $scanId)->get();
        $this->assertNotEmpty($findings);

        // 7. Verify report was generated
        $report = \App\Models\Report::where('options->scan_id', $scanId)->first();
        $this->assertNotNull($report);
        $this->assertEquals('Ready', $report->status);

        // 8. Verify PDF report download endpoint returns valid PDF
        $pdfRes = $this->actingAs($this->developer)->get("/api/scans/{$scanId}/report/download");
        $pdfRes->assertOk();
        $this->assertEquals('application/pdf', $pdfRes->headers->get('Content-Type'));
    }

    /**
     * Test 6: Rejection when an agent is running in container mode
     */
    public function test_agent_project_rejects_container_agent(): void
    {
        $containerAgentId = 'trustnode-local-agent-1';

        // Heartbeat as container agent
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $containerAgentId,
            'version' => '1.0.0',
            'os' => 'Linux',
            'execution_mode' => 'container',
            'desktop_picker_supported' => false,
        ])->assertOk();

        // Attempting to register agent project with container agent must be rejected
        $response = $this->actingAs($this->developer)->postJson('/api/projects', [
            'name' => 'Container Mode Project',
            'path' => '/home/dev/my-app',
            'source_type' => 'agent',
            'agent_id' => $containerAgentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => "The selected Agent ({$containerAgentId}) is running in container mode. A host desktop agent is required to monitor local directories."]);
    }

    /**
     * Test 7: Multi-project synchronization in heartbeat response
     */
    public function test_multi_project_heartbeat_sync(): void
    {
        $agentId = 'agent-multi-host-01';

        // Register heartbeat as desktop agent
        $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Windows',
            'execution_mode' => 'desktop',
            'desktop_picker_supported' => true,
        ])->assertOk();

        // Create 2 distinct projects assigned to this agent
        $proj1 = CodebaseProject::create([
            'name' => 'Project Alpha',
            'path' => 'C:\\Projects\\Alpha',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => true,
            'organization_id' => $this->organization->id,
            'created_by' => $this->developer->id,
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        $proj2 = CodebaseProject::create([
            'name' => 'Project Beta',
            'path' => 'C:\\Projects\\Beta',
            'source_type' => 'agent',
            'agent_id' => $agentId,
            'scan_profile' => 'full',
            'continuous_scan_enabled' => true,
            'organization_id' => $this->organization->id,
            'created_by' => $this->developer->id,
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        // Subsequent heartbeat from agent returns both assigned projects
        $hbRes = $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Windows',
            'execution_mode' => 'desktop',
            'desktop_picker_supported' => true,
        ]);

        $hbRes->assertOk();
        $assigned = $hbRes->json('assigned_projects');
        $this->assertCount(2, $assigned);

        $ids = array_column($assigned, 'id');
        $this->assertContains($proj1->id, $ids);
        $this->assertContains($proj2->id, $ids);

        // When project 1 is revoked, subsequent heartbeat only returns project 2
        $this->actingAs($this->developer)->deleteJson("/api/projects/{$proj1->id}")->assertOk();

        $hbRes2 = $this->actingAs($this->developer)->postJson('/api/agent/heartbeat', [
            'agent_id' => $agentId,
            'version' => '1.0.0',
            'os' => 'Windows',
            'execution_mode' => 'desktop',
            'desktop_picker_supported' => true,
        ]);

        $hbRes2->assertOk();
        $assigned2 = $hbRes2->json('assigned_projects');
        $this->assertCount(1, $assigned2);
        $this->assertEquals($proj2->id, $assigned2[0]['id']);
    }
}
