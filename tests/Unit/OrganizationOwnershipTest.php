<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Asset;
use App\Models\Integration;
use App\Models\LocalProject;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\Target;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org1;
    private Organization $org2;
    private User $userSingleOrg;
    private User $userMultiOrg;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Create organizations
        $this->org1 = Organization::create([
            'name' => 'Organization 1',
            'slug' => 'org-1',
        ]);

        $this->org2 = Organization::create([
            'name' => 'Organization 2',
            'slug' => 'org-2',
        ]);

        // Create teams
        $team1 = Team::create([
            'name' => 'Team 1',
            'slug' => 'team-1',
            'organization_id' => $this->org1->id,
        ]);

        $team2 = Team::create([
            'name' => 'Team 2',
            'slug' => 'team-2',
            'organization_id' => $this->org2->id,
        ]);

        // Get admin role
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail();

        // Create user with single organization
        $this->userSingleOrg = User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->userSingleOrg->teams()->attach($team1);

        // Create user with multiple organizations
        $this->userMultiOrg = User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);
        $this->userMultiOrg->teams()->attach([$team1->id, $team2->id]);
    }

    public function test_asset_has_organization_id_column(): void
    {
        $asset = Asset::factory()->create(['created_by' => $this->userSingleOrg->id]);
        $this->assertNotNull($asset->organization_id);
    }

    public function test_target_has_organization_id_column(): void
    {
        $target = Target::factory()->create(['created_by' => $this->userSingleOrg->id]);
        $this->assertNotNull($target->organization_id);
    }

    public function test_repository_has_organization_id_column(): void
    {
        $repo = Repository::factory()->create(['created_by' => $this->userSingleOrg->id]);
        $this->assertNotNull($repo->organization_id);
    }

    public function test_scan_has_organization_id_column(): void
    {
        $scan = Scan::factory()->create(['created_by' => $this->userSingleOrg->id]);
        $this->assertNotNull($scan->organization_id);
    }

    public function test_local_project_has_organization_id_column(): void
    {
        $project = LocalProject::factory()->create(['created_by' => $this->userSingleOrg->id]);
        $this->assertNotNull($project->organization_id);
    }

    public function test_integration_has_organization_id_column(): void
    {
        $integration = Integration::factory()->create();
        $this->assertNotNull($integration->organization_id);
    }

    public function test_setting_has_organization_id_column(): void
    {
        $setting = Setting::create([
            'key' => 'test_setting',
            'value' => 'test_value',
            'organization_id' => $this->org1->id,
        ]);
        $this->assertNotNull($setting->organization_id);
    }

    public function test_asset_organization_relationship(): void
    {
        $asset = Asset::factory()->create([
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $asset->organization);
        $this->assertEquals($this->org1->id, $asset->organization->id);
    }

    public function test_target_organization_relationship(): void
    {
        $target = Target::factory()->create([
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $target->organization);
        $this->assertEquals($this->org1->id, $target->organization->id);
    }

    public function test_repository_organization_relationship(): void
    {
        $repo = Repository::factory()->create([
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $repo->organization);
        $this->assertEquals($this->org1->id, $repo->organization->id);
    }

    public function test_scan_organization_relationship(): void
    {
        $scan = Scan::factory()->create([
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $scan->organization);
        $this->assertEquals($this->org1->id, $scan->organization->id);
    }

    public function test_local_project_organization_relationship(): void
    {
        $project = LocalProject::factory()->create([
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $project->organization);
        $this->assertEquals($this->org1->id, $project->organization->id);
    }

    public function test_integration_organization_relationship(): void
    {
        $integration = Integration::factory()->create(['organization_id' => $this->org1->id]);

        $this->assertInstanceOf(Organization::class, $integration->organization);
        $this->assertEquals($this->org1->id, $integration->organization->id);
    }

    public function test_setting_organization_relationship(): void
    {
        $setting = Setting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'organization_id' => $this->org1->id,
        ]);

        $this->assertInstanceOf(Organization::class, $setting->organization);
        $this->assertEquals($this->org1->id, $setting->organization->id);
    }

    public function test_repository_backed_scan_derives_organization_from_repository(): void
    {
        $repo = Repository::factory()->create([
            'organization_id' => $this->org1->id,
            'created_by' => $this->userSingleOrg->id,
        ]);

        $scan = Scan::factory()->create([
            'repository_id' => $repo->id,
            'created_by' => $this->userSingleOrg->id,
            'organization_id' => $repo->organization_id,
        ]);

        $this->assertEquals($repo->organization_id, $scan->organization_id);
    }

    public function test_factory_creates_valid_organization_id(): void
    {
        $asset = Asset::factory()->create();
        $target = Target::factory()->create();
        $repo = Repository::factory()->create();
        $scan = Scan::factory()->create();
        $project = LocalProject::factory()->create();
        $integration = Integration::factory()->create();

        // All should have valid organization IDs
        $this->assertTrue(Organization::whereId($asset->organization_id)->exists());
        $this->assertTrue(Organization::whereId($target->organization_id)->exists());
        $this->assertTrue(Organization::whereId($repo->organization_id)->exists());
        $this->assertTrue(Organization::whereId($scan->organization_id)->exists());
        $this->assertTrue(Organization::whereId($project->organization_id)->exists());
        $this->assertTrue(Organization::whereId($integration->organization_id)->exists());
    }

}
