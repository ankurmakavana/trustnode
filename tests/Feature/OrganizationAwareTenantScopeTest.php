<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\LocalProject;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Models\Setting;
use App\Models\Target;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OrganizationAwareTenantScopeTest
 *
 * Security regression tests for organization-aware tenant scope.
 * Verifies cross-organization access is blocked and single-org access works.
 */
class OrganizationAwareTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private Organization $orgC;

    private Team $teamA;
    private Team $teamB;
    private Team $teamC;

    private User $userOrgA;
    private User $userOrgB;
    private User $userOrgAandB;
    private User $userNoOrg;

    private Asset $assetOrgA;
    private Asset $assetOrgB;
    private Target $targetOrgA;
    private Target $targetOrgB;
    private Repository $repoOrgA;
    private Repository $repoOrgB;
    private LocalProject $projectOrgA;
    private LocalProject $projectOrgB;
    private Scan $scanOrgA;
    private Scan $scanOrgB;
    private Integration $integrationOrgA;
    private Integration $integrationOrgB;
    private Setting $settingOrgA;
    private Setting $settingOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        // Create organizations
        $this->orgA = Organization::create(['name' => 'Org A', 'slug' => 'org-a']);
        $this->orgB = Organization::create(['name' => 'Org B', 'slug' => 'org-b']);
        $this->orgC = Organization::create(['name' => 'Org C', 'slug' => 'org-c']);

        // Create teams
        $this->teamA = Team::create(['name' => 'Team A', 'slug' => 'team-a', 'organization_id' => $this->orgA->id]);
        $this->teamB = Team::create(['name' => 'Team B', 'slug' => 'team-b', 'organization_id' => $this->orgB->id]);
        $this->teamC = Team::create(['name' => 'Team C', 'slug' => 'team-c', 'organization_id' => $this->orgC->id]);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail();

        // Create users
        $this->userOrgA = User::factory()->create(['role_id' => $adminRole->id, 'status' => UserStatus::ACTIVE]);
        $this->userOrgA->teams()->attach($this->teamA);

        $this->userOrgB = User::factory()->create(['role_id' => $adminRole->id, 'status' => UserStatus::ACTIVE]);
        $this->userOrgB->teams()->attach($this->teamB);

        $this->userOrgAandB = User::factory()->create(['role_id' => $adminRole->id, 'status' => UserStatus::ACTIVE]);
        $this->userOrgAandB->teams()->attach([$this->teamA->id, $this->teamB->id]);

        $this->userNoOrg = User::factory()->create(['role_id' => $adminRole->id, 'status' => UserStatus::ACTIVE]);

        // Create organization-owned resources
        $this->assetOrgA = Asset::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userOrgA->id]);
        $this->assetOrgB = Asset::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userOrgB->id]);

        $this->targetOrgA = Target::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userOrgA->id]);
        $this->targetOrgB = Target::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userOrgB->id]);

        $this->repoOrgA = Repository::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userOrgA->id]);
        $this->repoOrgB = Repository::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userOrgB->id]);

        $this->projectOrgA = LocalProject::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userOrgA->id]);
        $this->projectOrgB = LocalProject::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userOrgB->id]);

        $this->scanOrgA = Scan::factory()->create(['organization_id' => $this->orgA->id, 'created_by' => $this->userOrgA->id]);
        $this->scanOrgB = Scan::factory()->create(['organization_id' => $this->orgB->id, 'created_by' => $this->userOrgB->id]);

        $this->integrationOrgA = Integration::factory()->create(['organization_id' => $this->orgA->id]);
        $this->integrationOrgB = Integration::factory()->create(['organization_id' => $this->orgB->id]);

        $this->settingOrgA = Setting::create(['key' => 'setting_a', 'value' => 'value_a', 'organization_id' => $this->orgA->id]);
        $this->settingOrgB = Setting::create(['key' => 'setting_b', 'value' => 'value_b', 'organization_id' => $this->orgB->id]);
    }

    // =====================================================================
    // Single-Organization Access Tests
    // =====================================================================

    public function test_user_can_query_their_organizations_assets(): void
    {
        $this->actingAs($this->userOrgA);
        $assets = Asset::query()->get();
        $this->assertTrue($assets->contains('id', $this->assetOrgA->id));
        $this->assertFalse($assets->contains('id', $this->assetOrgB->id));
    }

    public function test_user_can_query_their_organizations_targets(): void
    {
        $this->actingAs($this->userOrgA);
        $targets = Target::query()->get();
        $this->assertTrue($targets->contains('id', $this->targetOrgA->id));
        $this->assertFalse($targets->contains('id', $this->targetOrgB->id));
    }

    public function test_user_can_query_their_organizations_repositories(): void
    {
        $this->actingAs($this->userOrgA);
        $repos = Repository::query()->get();
        $this->assertTrue($repos->contains('id', $this->repoOrgA->id));
        $this->assertFalse($repos->contains('id', $this->repoOrgB->id));
    }

    public function test_user_can_query_their_organizations_scans(): void
    {
        $this->actingAs($this->userOrgA);
        $scans = Scan::query()->get();
        $this->assertTrue($scans->contains('id', $this->scanOrgA->id));
        $this->assertFalse($scans->contains('id', $this->scanOrgB->id));
    }

    public function test_user_can_query_their_organizations_local_projects(): void
    {
        $this->actingAs($this->userOrgA);
        $projects = LocalProject::query()->get();
        $this->assertTrue($projects->contains('id', $this->projectOrgA->id));
        $this->assertFalse($projects->contains('id', $this->projectOrgB->id));
    }

    public function test_user_can_query_their_organizations_integrations(): void
    {
        $this->actingAs($this->userOrgA);
        $integrations = Integration::query()->get();
        $this->assertTrue($integrations->contains('id', $this->integrationOrgA->id));
        $this->assertFalse($integrations->contains('id', $this->integrationOrgB->id));
    }

    public function test_user_can_query_their_organizations_settings(): void
    {
        $this->actingAs($this->userOrgA);
        $settings = Setting::query()->get();
        $this->assertTrue($settings->contains('id', $this->settingOrgA->id));
        $this->assertFalse($settings->contains('id', $this->settingOrgB->id));
    }

    // =====================================================================
    // Cross-Organization Access Tests
    // =====================================================================

    public function test_user_cannot_access_other_organization_assets_by_query(): void
    {
        $this->actingAs($this->userOrgA);
        $assets = Asset::query()->get();
        $this->assertFalse($assets->contains('id', $this->assetOrgB->id));
    }

    public function test_user_cannot_access_other_organization_assets_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $asset = Asset::find($this->assetOrgB->id);
        $this->assertNull($asset);
    }

    public function test_user_cannot_access_other_organization_targets_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $target = Target::find($this->targetOrgB->id);
        $this->assertNull($target);
    }

    public function test_user_cannot_access_other_organization_repositories_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $repo = Repository::find($this->repoOrgB->id);
        $this->assertNull($repo);
    }

    public function test_user_cannot_access_other_organization_scans_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $scan = Scan::find($this->scanOrgB->id);
        $this->assertNull($scan);
    }

    public function test_user_cannot_access_other_organization_local_projects_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $project = LocalProject::find($this->projectOrgB->id);
        $this->assertNull($project);
    }

    public function test_user_cannot_access_other_organization_integrations_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $integration = Integration::find($this->integrationOrgB->id);
        $this->assertNull($integration);
    }

    public function test_user_cannot_access_other_organization_settings_by_id(): void
    {
        $this->actingAs($this->userOrgA);
        $setting = Setting::find($this->settingOrgB->id);
        $this->assertNull($setting);
    }

    // =====================================================================
    // Multi-Organization Access Tests
    // =====================================================================

    public function test_user_with_multiple_organizations_can_access_both(): void
    {
        $this->actingAs($this->userOrgAandB);

        $assets = Asset::query()->get();
        $this->assertTrue($assets->contains('id', $this->assetOrgA->id));
        $this->assertTrue($assets->contains('id', $this->assetOrgB->id));
        $this->assertFalse($assets->contains('id', Asset::factory()->create(['organization_id' => $this->orgC->id])->id));
    }

    public function test_multi_org_user_cannot_access_unrelated_organization(): void
    {
        $this->actingAs($this->userOrgAandB);

        $assetC = Asset::factory()->create(['organization_id' => $this->orgC->id]);
        $assets = Asset::query()->get();
        $this->assertFalse($assets->contains('id', $assetC->id));
    }

    // =====================================================================
    // Zero-Organization User Tests
    // =====================================================================

    public function test_user_with_no_organizations_cannot_access_any_resources(): void
    {
        $this->actingAs($this->userNoOrg);

        $assets = Asset::query()->get();
        $targets = Target::query()->get();
        $repos = Repository::query()->get();
        $scans = Scan::query()->get();
        $projects = LocalProject::query()->get();
        $integrations = Integration::query()->get();
        $settings = Setting::query()->get();

        $this->assertEmpty($assets);
        $this->assertEmpty($targets);
        $this->assertEmpty($repos);
        $this->assertEmpty($scans);
        $this->assertEmpty($projects);
        $this->assertEmpty($integrations);
        $this->assertEmpty($settings);
    }

    // =====================================================================
    // Derived Resource Tests (Should Not Bypass Parent Scope)
    // =====================================================================

    public function test_finding_access_cannot_bypass_scan_organization_boundary(): void
    {
        // Create finding in Org B scan
        $findingOrgB = Finding::factory()->create(['scan_id' => $this->scanOrgB->id, 'created_by' => $this->userOrgB->id]);

        // User A tries to access
        $this->actingAs($this->userOrgA);
        $finding = Finding::find($findingOrgB->id);

        // Finding should be inaccessible (TenantScope filters by scan's created_by, which is Org B's user)
        $this->assertNull($finding);
    }

    public function test_scan_report_access_cannot_bypass_scan_organization_boundary(): void
    {
        // Create report for Org B scan
        $reportOrgB = ScanReport::create([
            'scan_id' => $this->scanOrgB->id,
            'requested_by' => $this->userOrgB->id,
            'status' => 'completed',
            'file_path' => 'reports/test.pdf',
        ]);

        // User A tries to access
        $this->actingAs($this->userOrgA);
        $report = ScanReport::find($reportOrgB->id);

        // Report should be inaccessible (TenantScope filters by requested_by user)
        $this->assertNull($report);
    }

    public function test_integration_credential_access_cannot_bypass_integration_organization_boundary(): void
    {
        $credentialOrgB = IntegrationCredential::create([
            'integration_id' => $this->integrationOrgB->id,
            'key' => 'api-key',
            'value' => 'secret',
        ]);

        $this->actingAs($this->userOrgA);
        $this->assertNull(IntegrationCredential::find($credentialOrgB->id));

        $this->actingAs($this->userOrgB);
        $this->assertNotNull(IntegrationCredential::find($credentialOrgB->id));
    }

    // =====================================================================
    // Non-Organization-Scoped Model Tests
    // =====================================================================

    public function test_user_level_isolation_unchanged_for_non_org_models(): void
    {
        $notificationA = Notification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->userOrgA->id,
            'data' => json_encode(['message' => 'Org A notification']),
            'created_by' => $this->userOrgA->id,
        ]);

        $this->actingAs($this->userOrgB);
        $this->assertNull(Notification::find($notificationA->id));

        $this->actingAs($this->userOrgA);
        $this->assertNotNull(Notification::find($notificationA->id));
    }
}
