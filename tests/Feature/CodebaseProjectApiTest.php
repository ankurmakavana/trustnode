<?php

namespace Tests\Feature;

use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CodebaseProjectApiTest extends TestCase
{
    use RefreshDatabase;

    protected $tempWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Set up temporary workspace directory for testing
        $this->tempWorkspace = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'trustnode_test_workspace_' . uniqid();
        mkdir($this->tempWorkspace, 0777, true);
        mkdir($this->tempWorkspace . DIRECTORY_SEPARATOR . 'valid-project');
        mkdir($this->tempWorkspace . DIRECTORY_SEPARATOR . 'another-project');
        file_put_contents($this->tempWorkspace . DIRECTORY_SEPARATOR . 'not-a-dir.txt', 'test');

        config(['agent.scan_mount_dir' => $this->tempWorkspace]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempWorkspace);
        parent::tearDown();
    }

    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createUserWithPermissions(array $permissions = []): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'test-role-' . md5(implode(',', $permissions))],
            ['name' => 'Test Role', 'description' => 'Test']
        );
        
        foreach ($permissions as $perm) {
            \App\Models\Permission::firstOrCreate(['name' => $perm]);
        }

        $user = User::factory()->create(['role_id' => $role->id]);
        $org = Organization::factory()->create();
        $team = Team::factory()->create(['organization_id' => $org->id]);
        $team->users()->attach($user->id);

        $user->syncPermissions($permissions);

        return $user;
    }

    public function test_unauthenticated_access_denied()
    {
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/projects', [])->assertUnauthorized();
        $this->getJson('/api/projects/workspace-directories')->assertUnauthorized();
    }

    public function test_authenticated_project_listing()
    {
        $user = $this->createUserWithPermissions(['targets.view']);
        $orgId = $user->teams()->first()->organization_id;

        CodebaseProject::factory()->create([
            'organization_id' => $orgId,
            'created_by' => $user->id,
            'name' => 'Project A'
        ]);

        $response = $this->actingAs($user)->getJson('/api/projects');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Project A');
    }

    public function test_tenant_isolation_listing()
    {
        $userA = $this->createUserWithPermissions(['targets.view']);
        $userB = $this->createUserWithPermissions(['targets.view']);

        CodebaseProject::factory()->create([
            'organization_id' => $userA->teams()->first()->organization_id,
            'created_by' => $userA->id,
            'name' => 'Org A Project'
        ]);
        CodebaseProject::factory()->create([
            'organization_id' => $userB->teams()->first()->organization_id,
            'created_by' => $userB->id,
            'name' => 'Org B Project'
        ]);

        $response = $this->actingAs($userA)->getJson('/api/projects');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Org A Project');
    }

    public function test_workspace_directory_discovery()
    {
        $user = $this->createUserWithPermissions(['targets.view']);
        
        $response = $this->actingAs($user)->getJson('/api/projects/workspace-directories');
        $response->assertOk();
        $directories = $response->json('directories');
        
        $this->assertContains('valid-project', $directories);
        $this->assertContains('another-project', $directories);
        $this->assertNotContains('not-a-dir.txt', $directories);
    }

    public function test_unavailable_workspace_handled_safely()
    {
        config(['agent.scan_mount_dir' => '/path/does/not/exist/for/sure']);
        $user = $this->createUserWithPermissions(['targets.view']);
        
        $response = $this->actingAs($user)->getJson('/api/projects/workspace-directories');
        $response->assertStatus(503);
        $response->assertJsonPath('message', 'Workspace mount is unavailable or not configured correctly.');
    }

    public function test_successful_project_creation()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Valid Project',
            'path' => 'valid-project',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertCreated();
        $this->assertDatabaseHas('codebase_projects', [
            'name' => 'Valid Project',
            'path' => 'valid-project',
            'created_by' => $user->id,
            'organization_id' => $user->teams()->first()->organization_id,
        ]);
    }

    public function test_invalid_absolute_path_rejected()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Abs Path',
            'path' => '/etc',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertStatus(422);
    }

    public function test_traversal_rejected()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Traversal',
            'path' => '../secret',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertStatus(422);
    }

    public function test_nonexistent_path_rejected()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Non Existent',
            'path' => 'fake-dir',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertStatus(422);
    }

    public function test_file_path_rejected()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'File',
            'path' => 'not-a-dir.txt',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertStatus(422);
    }

    public function test_duplicate_path_rejected()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        $orgId = $user->teams()->first()->organization_id;

        CodebaseProject::factory()->create([
            'organization_id' => $orgId,
            'created_by' => $user->id,
            'path' => 'valid-project'
        ]);
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Duplicate',
            'path' => 'valid-project',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['path']);
    }

    public function test_organization_id_and_created_by_cannot_be_spoofed()
    {
        $user = $this->createUserWithPermissions(['targets.create']);
        $orgId = $user->teams()->first()->organization_id;
        
        $response = $this->actingAs($user)->postJson('/api/projects', [
            'name' => 'Spoof',
            'path' => 'valid-project',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
            'organization_id' => 9999,
            'created_by' => 9999,
        ]);
        
        $response->assertCreated();
        $this->assertDatabaseHas('codebase_projects', [
            'name' => 'Spoof',
            'created_by' => $user->id,
            'organization_id' => $orgId,
        ]);
    }

    public function test_cross_tenant_delete_denied()
    {
        $userA = $this->createUserWithPermissions(['targets.delete']);
        $userB = $this->createUserWithPermissions(['targets.delete']);

        $projectA = CodebaseProject::factory()->create([
            'organization_id' => $userA->teams()->first()->organization_id,
            'created_by' => $userA->id,
        ]);

        $response = $this->actingAs($userB)->deleteJson('/api/projects/' . $projectA->id);
        $response->assertNotFound(); // Implicit binding uses global scopes
    }

    public function test_soft_revoke_delete()
    {
        $user = $this->createUserWithPermissions(['targets.delete']);
        $project = CodebaseProject::factory()->create([
            'organization_id' => $user->teams()->first()->organization_id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/projects/' . $project->id);
        $response->assertOk();

        $this->assertSoftDeleted('codebase_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('codebase_projects', [
            'id' => $project->id,
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ARCHIVED->value
        ]);
    }

    public function test_sibling_directory_sharing_the_mount_name_prefix_is_rejected_on_create()
    {
        // "<mount>-sibling" shares the mount's string prefix but lies outside the mount.
        $sibling = $this->tempWorkspace . '-sibling';
        mkdir($sibling);

        try {
            $user = $this->createUserWithPermissions(['targets.create']);

            $response = $this->actingAs($user)->postJson('/api/projects', [
                'name' => 'Sibling',
                'path' => '../' . basename($sibling),
                'scan_profile' => 'standard',
                'continuous_scan_enabled' => false,
            ]);

            $response->assertStatus(422);
            $this->assertDatabaseMissing('codebase_projects', ['path' => '../' . basename($sibling)]);
        } finally {
            rmdir($sibling);
        }
    }

    public function test_scan_rejects_project_path_resolving_to_a_sibling_directory()
    {
        $sibling = $this->tempWorkspace . '-sibling';
        mkdir($sibling);

        try {
            $user = $this->createUserWithPermissions(['scans.execute']);
            $project = CodebaseProject::factory()->create([
                'organization_id' => $user->teams()->first()->organization_id,
                'created_by' => $user->id,
                'path' => '../' . basename($sibling),
            ]);

            DB::table('agent_states')->insert([
                'agent_id' => 'test-agent',
                'state' => json_encode(['state' => 'running']),
            ]);

            $response = $this->actingAs($user)->postJson('/api/projects/' . $project->id . '/scan');

            $response->assertStatus(422);
        } finally {
            rmdir($sibling);
        }
    }

    public function test_valid_scan_request_behavior()
    {
        $user = $this->createUserWithPermissions(['scans.execute']);
        $project = CodebaseProject::factory()->create([
            'organization_id' => $user->teams()->first()->organization_id,
            'created_by' => $user->id,
            'path' => 'valid-project'
        ]);

        // Mock active agent
        DB::table('agent_states')->insert([
            'agent_id' => 'test-agent',
            'state' => json_encode(['state' => 'running']),
        ]);

        $response = $this->actingAs($user)->postJson('/api/projects/' . $project->id . '/scan');
        
        $response->assertOk();
        $response->assertJsonPath('message', 'Scan queued successfully.');
    }

    public function test_cross_tenant_scan_denied()
    {
        $userA = $this->createUserWithPermissions(['scans.execute']);
        $userB = $this->createUserWithPermissions(['scans.execute']);

        $projectA = CodebaseProject::factory()->create([
            'organization_id' => $userA->teams()->first()->organization_id,
            'created_by' => $userA->id,
            'path' => 'valid-project'
        ]);

        $response = $this->actingAs($userB)->postJson('/api/projects/' . $projectA->id . '/scan');
        $response->assertNotFound();
    }
}
