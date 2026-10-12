<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Enums\CodebaseProject\ScanProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CodebaseProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    /**
     * Helper: create a user linked to an organization via a team.
     */
    private function createUserWithOrg(?Organization $org = null): array
    {
        $org = $org ?? Organization::factory()->create();
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $adminRole->id]);
        $team = Team::factory()->create(['organization_id' => $org->id]);
        $team->users()->attach($user->id);

        return [$user, $org, $team];
    }

    // ---------------------------------------------------------------
    // 1. Creation
    // ---------------------------------------------------------------
    public function test_codebase_project_can_be_created()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'My SaaS Backend',
            'path' => 'projects/my-saas',
            'scan_profile' => ScanProfile::STANDARD,
            'continuous_scan_enabled' => false,
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('codebase_projects', [
            'id' => $project->id,
            'name' => 'My SaaS Backend',
            'path' => 'projects/my-saas',
        ]);
    }

    // ---------------------------------------------------------------
    // 2. Required fields
    // ---------------------------------------------------------------
    public function test_name_is_required()
    {
        [$user, $org] = $this->createUserWithOrg();

        $this->expectException(\Illuminate\Database\QueryException::class);

        CodebaseProject::withoutGlobalScopes()->create([
            'path' => 'some-project',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_path_is_required()
    {
        [$user, $org] = $this->createUserWithOrg();

        $this->expectException(\Illuminate\Database\QueryException::class);

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Test Project',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_organization_id_is_required()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Test Project',
            'path' => 'some-path',
            'created_by' => 1,
        ]);
    }

    // ---------------------------------------------------------------
    // 3. UUID generation
    // ---------------------------------------------------------------
    public function test_uuid_is_auto_generated_on_creation()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'UUID Test Project',
            'path' => 'uuid-test',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->assertNotEmpty($project->uuid);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($project->uuid));
    }

    public function test_explicit_uuid_is_preserved()
    {
        [$user, $org] = $this->createUserWithOrg();

        $explicitUuid = (string) \Illuminate\Support\Str::uuid();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'uuid' => $explicitUuid,
            'name' => 'Explicit UUID Project',
            'path' => 'explicit-uuid-test',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals($explicitUuid, $project->uuid);
    }

    // ---------------------------------------------------------------
    // 4. Organization ownership
    // ---------------------------------------------------------------
    public function test_project_belongs_to_organization()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Org Test',
            'path' => 'org-test',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(Organization::class, $project->organization);
        $this->assertEquals($org->id, $project->organization->id);
    }

    // ---------------------------------------------------------------
    // 5. Created-by relationship
    // ---------------------------------------------------------------
    public function test_project_belongs_to_creator()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Creator Test',
            'path' => 'creator-test',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->assertInstanceOf(User::class, $project->creator);
        $this->assertEquals($user->id, $project->creator->id);
    }

    // ---------------------------------------------------------------
    // 6. Soft deletion
    // ---------------------------------------------------------------
    public function test_soft_delete_hides_project_from_default_queries()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Soft Delete Test',
            'path' => 'soft-delete-test',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $project->delete();

        $this->assertSoftDeleted('codebase_projects', ['id' => $project->id]);
        $this->assertNull(
            CodebaseProject::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->find($project->id)
        );
        $this->assertNotNull(
            CodebaseProject::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->withTrashed()->find($project->id)
        );
    }

    // ---------------------------------------------------------------
    // 7. Tenant isolation
    // ---------------------------------------------------------------
    public function test_tenant_scope_isolates_projects_by_organization()
    {
        [$userA, $orgA] = $this->createUserWithOrg();
        [$userB, $orgB] = $this->createUserWithOrg();

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Org A Project',
            'path' => 'org-a-project',
            'organization_id' => $orgA->id,
            'created_by' => $userA->id,
        ]);

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Org B Project',
            'path' => 'org-b-project',
            'organization_id' => $orgB->id,
            'created_by' => $userB->id,
        ]);

        // Acting as user A — should only see org A's project
        $this->actingAs($userA);
        $visibleProjects = CodebaseProject::all();

        $this->assertCount(1, $visibleProjects);
        $this->assertEquals('Org A Project', $visibleProjects->first()->name);
    }

    public function test_cross_tenant_access_is_denied()
    {
        [$userA, $orgA] = $this->createUserWithOrg();
        [$userB, $orgB] = $this->createUserWithOrg();

        $projectB = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Secret Project',
            'path' => 'secret-project',
            'organization_id' => $orgB->id,
            'created_by' => $userB->id,
        ]);

        // Acting as user A — should not find org B's project
        $this->actingAs($userA);
        $found = CodebaseProject::find($projectB->id);

        $this->assertNull($found, 'User from org A must not access org B project.');
    }

    // ---------------------------------------------------------------
    // 8. Valid relative paths
    // ---------------------------------------------------------------
    public function test_valid_relative_paths_can_be_stored()
    {
        [$user, $org] = $this->createUserWithOrg();

        $validPaths = [
            'MyCodebase',
            'projects/my-saas',
            'client-a/backend',
            'services/auth-service',
        ];

        foreach ($validPaths as $path) {
            $project = CodebaseProject::withoutGlobalScopes()->create([
                'name' => 'Project for ' . $path,
                'path' => $path,
                'organization_id' => $org->id,
                'created_by' => $user->id,
            ]);

            $this->assertEquals($path, $project->path);
        }
    }

    // ---------------------------------------------------------------
    // 9. Absolute paths are not normalized by the model
    // ---------------------------------------------------------------
    public function test_model_stores_path_as_given_without_normalization()
    {
        [$user, $org] = $this->createUserWithOrg();

        // The model itself does not prevent storing an absolute path.
        // Path boundary validation is the Agent capability's responsibility.
        // This test confirms the model does NOT silently normalize or strip paths.
        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Abs Path Test',
            'path' => 'C:\\Users\\Developer\\Projects\\MyCodebase',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals('C:\\Users\\Developer\\Projects\\MyCodebase', $project->path);
    }

    // ---------------------------------------------------------------
    // Enum casts
    // ---------------------------------------------------------------
    public function test_scan_profile_is_cast_to_enum()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Enum Cast Test',
            'path' => 'enum-test',
            'scan_profile' => ScanProfile::FULL,
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $project->refresh();
        $this->assertInstanceOf(ScanProfile::class, $project->scan_profile);
        $this->assertEquals(ScanProfile::FULL, $project->scan_profile);
    }

    public function test_status_is_cast_to_enum()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Status Enum Test',
            'path' => 'status-enum-test',
            'status' => CodebaseProjectStatus::ARCHIVED,
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $project->refresh();
        $this->assertInstanceOf(CodebaseProjectStatus::class, $project->status);
        $this->assertEquals(CodebaseProjectStatus::ARCHIVED, $project->status);
    }

    public function test_continuous_scan_enabled_is_cast_to_boolean()
    {
        [$user, $org] = $this->createUserWithOrg();

        $project = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Bool Cast Test',
            'path' => 'bool-test',
            'continuous_scan_enabled' => true,
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $project->refresh();
        $this->assertIsBool($project->continuous_scan_enabled);
        $this->assertTrue($project->continuous_scan_enabled);
    }

    // ---------------------------------------------------------------
    // Unique constraint: org + path
    // ---------------------------------------------------------------
    public function test_duplicate_path_within_same_organization_is_rejected()
    {
        [$user, $org] = $this->createUserWithOrg();

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Project A',
            'path' => 'shared-path',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Project B',
            'path' => 'shared-path',
            'organization_id' => $org->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_same_path_in_different_organizations_is_allowed()
    {
        [$userA, $orgA] = $this->createUserWithOrg();
        [$userB, $orgB] = $this->createUserWithOrg();

        $projectA = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Org A Project',
            'path' => 'shared-path',
            'organization_id' => $orgA->id,
            'created_by' => $userA->id,
        ]);

        $projectB = CodebaseProject::withoutGlobalScopes()->create([
            'name' => 'Org B Project',
            'path' => 'shared-path',
            'organization_id' => $orgB->id,
            'created_by' => $userB->id,
        ]);

        $this->assertNotEquals($projectA->id, $projectB->id);
        $this->assertEquals('shared-path', $projectA->path);
        $this->assertEquals('shared-path', $projectB->path);
    }

    // ---------------------------------------------------------------
    // Factory
    // ---------------------------------------------------------------
    public function test_factory_creates_valid_project()
    {
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->firstOrFail();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $project = CodebaseProject::factory()->create([
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($project->id);
        $this->assertNotEmpty($project->uuid);
        $this->assertNotEmpty($project->name);
        $this->assertNotEmpty($project->path);
        $this->assertNotNull($project->organization_id);
        $this->assertNotNull($project->created_by);
        $this->assertInstanceOf(ScanProfile::class, $project->scan_profile);
        $this->assertInstanceOf(CodebaseProjectStatus::class, $project->status);
    }
}
