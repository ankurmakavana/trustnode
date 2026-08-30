<?php

namespace Tests\Feature;

use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\FindingLifecycleHistory;
use App\Models\LocalProject;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityPostureDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenantA;
    protected User $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->tenantA = User::factory()->create([
            'role_id' => $adminRole->id,
            'name' => 'Tenant A Admin',
        ]);

        $this->tenantB = User::factory()->create([
            'role_id' => $adminRole->id,
            'name' => 'Tenant B Admin',
        ]);
    }

    /**
     * Helper to create a scan.
     */
    protected function createScan(User $user, array $attributes = []): Scan
    {
        return Scan::create(array_merge([
            'name' => 'Test Scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => 'https://github.com/example/repo',
            'status' => ScanStatus::COMPLETED,
            'progress' => 100,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(5),
            'created_by' => $user->id,
        ], $attributes));
    }

    /**
     * 1-5. Test dashboard returns accurate lifecycle summary (new, recurring, resolved, regression).
     */
    public function test_dashboard_returns_accurate_lifecycle_summary()
    {
        // Create identities for Tenant A
        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'ident-1'),
            'fingerprint' => 'fp-1',
            'lifecycle_status' => FindingLifecycleStatus::NEW,
            'created_by' => $this->tenantA->id,
        ]);

        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'ident-2'),
            'fingerprint' => 'fp-2',
            'lifecycle_status' => FindingLifecycleStatus::RECURRING,
            'created_by' => $this->tenantA->id,
        ]);

        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'ident-3'),
            'fingerprint' => 'fp-3',
            'lifecycle_status' => FindingLifecycleStatus::RESOLVED,
            'resolved_at' => now(),
            'created_by' => $this->tenantA->id,
        ]);

        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'ident-4'),
            'fingerprint' => 'fp-4',
            'lifecycle_status' => FindingLifecycleStatus::REGRESSION,
            'created_by' => $this->tenantA->id,
        ]);

        $response = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('lifecycleSummary.new', 1)
            ->assertJsonPath('lifecycleSummary.recurring', 1)
            ->assertJsonPath('lifecycleSummary.resolved', 1)
            ->assertJsonPath('lifecycleSummary.regression', 1);
    }

    /**
     * 6-10. Multiple successful scans produce ordered trend points with accurate deltas.
     */
    public function test_historical_posture_trend_points_and_delta_calculations()
    {
        $repo = Repository::create([
            'name' => 'Backend API',
            'repository_url' => 'https://github.com/org/api.git',
            'default_branch' => 'main',
            'created_by' => $this->tenantA->id,
        ]);

        // Scan 1: 3 critical, 1 high -> total 4 (Baseline: net_change 0)
        $scan1 = $this->createScan($this->tenantA, [
            'name' => 'Scan 1',
            'repository_id' => $repo->id,
            'created_at' => now()->subHours(3),
            'completed_at' => now()->subHours(3)->addMinutes(5),
        ]);

        Finding::factory()->count(3)->create([
            'scan_id' => $scan1->id,
            'severity' => FindingSeverity::CRITICAL,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);
        Finding::factory()->count(1)->create([
            'scan_id' => $scan1->id,
            'severity' => FindingSeverity::HIGH,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        // Scan 2: 1 critical, 1 high -> total 2 (net_change: -2, posture improving)
        $scan2 = $this->createScan($this->tenantA, [
            'name' => 'Scan 2',
            'repository_id' => $repo->id,
            'created_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2)->addMinutes(5),
        ]);

        Finding::factory()->count(1)->create([
            'scan_id' => $scan2->id,
            'severity' => FindingSeverity::CRITICAL,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);
        Finding::factory()->count(1)->create([
            'scan_id' => $scan2->id,
            'severity' => FindingSeverity::HIGH,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        $response = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('postureAssessment', 'improving')
            ->assertJsonCount(2, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scan1->id)
            ->assertJsonPath('postureTrend.0.total_open', 4)
            ->assertJsonPath('postureTrend.0.critical', 3)
            ->assertJsonPath('postureTrend.0.high', 1)
            ->assertJsonPath('postureTrend.0.net_change', 0)
            ->assertJsonPath('postureTrend.1.scan_id', $scan2->id)
            ->assertJsonPath('postureTrend.1.total_open', 2)
            ->assertJsonPath('postureTrend.1.critical', 1)
            ->assertJsonPath('postureTrend.1.high', 1)
            ->assertJsonPath('postureTrend.1.net_change', -2);
    }

    /**
     * 11-12. Failed scan is excluded from posture trend and does not alter posture.
     */
    public function test_failed_scan_is_excluded_from_trend()
    {
        $repo = Repository::create([
            'name' => 'Service Repo',
            'repository_url' => 'https://github.com/org/svc.git',
            'default_branch' => 'main',
            'created_by' => $this->tenantA->id,
        ]);

        $scan1 = $this->createScan($this->tenantA, [
            'name' => 'Scan 1',
            'repository_id' => $repo->id,
            'status' => ScanStatus::COMPLETED,
        ]);
        Finding::factory()->count(2)->create([
            'scan_id' => $scan1->id,
            'severity' => FindingSeverity::HIGH,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        // Failed scan with 0 findings or errors
        $this->createScan($this->tenantA, [
            'name' => 'Scan Failed',
            'repository_id' => $repo->id,
            'status' => ScanStatus::FAILED,
        ]);

        $response = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scan1->id);
    }

    /**
     * 13-14. Zero-finding successful scan is a valid historical point.
     */
    public function test_zero_finding_successful_scan_in_posture_trend()
    {
        $repo = Repository::create([
            'name' => 'Clean Repo',
            'repository_url' => 'https://github.com/org/clean.git',
            'default_branch' => 'main',
            'created_by' => $this->tenantA->id,
        ]);

        $scan1 = $this->createScan($this->tenantA, [
            'name' => 'Scan 1',
            'repository_id' => $repo->id,
        ]);
        Finding::factory()->count(3)->create([
            'scan_id' => $scan1->id,
            'severity' => FindingSeverity::MEDIUM,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        // Scan 2 with zero findings
        $scan2 = $this->createScan($this->tenantA, [
            'name' => 'Scan 2 Clean',
            'repository_id' => $repo->id,
        ]);

        $response = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'postureTrend')
            ->assertJsonPath('postureTrend.1.scan_id', $scan2->id)
            ->assertJsonPath('postureTrend.1.total_open', 0)
            ->assertJsonPath('postureTrend.1.net_change', -3)
            ->assertJsonPath('postureAssessment', 'improving');
    }

    /**
     * 15-17. Tenant isolation: Tenant A cannot see Tenant B counts or trend points.
     */
    public function test_tenant_isolation_on_dashboard_and_trends()
    {
        // Tenant A scan & identity
        $scanA = $this->createScan($this->tenantA, ['name' => 'Tenant A Scan']);
        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'tenant-a-ident'),
            'fingerprint' => 'fp-a',
            'lifecycle_status' => FindingLifecycleStatus::NEW,
            'created_by' => $this->tenantA->id,
        ]);
        Finding::factory()->count(2)->create([
            'scan_id' => $scanA->id,
            'severity' => FindingSeverity::CRITICAL,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        // Tenant B scan & identity
        $scanB = $this->createScan($this->tenantB, ['name' => 'Tenant B Scan']);
        FindingIdentity::create([
            'identity_hash' => hash('sha256', 'tenant-b-ident'),
            'fingerprint' => 'fp-b',
            'lifecycle_status' => FindingLifecycleStatus::REGRESSION,
            'created_by' => $this->tenantB->id,
        ]);
        Finding::factory()->count(5)->create([
            'scan_id' => $scanB->id,
            'severity' => FindingSeverity::CRITICAL,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantB->id,
        ]);

        // Request as Tenant A
        $resA = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats');
        $resA->assertStatus(200)
            ->assertJsonPath('lifecycleSummary.new', 1)
            ->assertJsonPath('lifecycleSummary.regression', 0)
            ->assertJsonCount(1, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scanA->id)
            ->assertJsonPath('postureTrend.0.critical', 2);

        // Request as Tenant B
        $resB = $this->actingAs($this->tenantB)->getJson('/api/dashboard/stats');
        $resB->assertStatus(200)
            ->assertJsonPath('lifecycleSummary.new', 0)
            ->assertJsonPath('lifecycleSummary.regression', 1)
            ->assertJsonCount(1, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scanB->id)
            ->assertJsonPath('postureTrend.0.critical', 5);
    }

    /**
     * 18-20. Target filtering isolation (repo vs local vs infra target).
     */
    public function test_target_filtering_isolation()
    {
        $repo1 = Repository::create([
            'name' => 'Repo 1',
            'repository_url' => 'https://github.com/org/repo1.git',
            'default_branch' => 'main',
            'created_by' => $this->tenantA->id,
        ]);

        $repo2 = Repository::create([
            'name' => 'Repo 2',
            'repository_url' => 'https://github.com/org/repo2.git',
            'default_branch' => 'main',
            'created_by' => $this->tenantA->id,
        ]);

        $scanRepo1 = $this->createScan($this->tenantA, [
            'name' => 'Scan Repo 1',
            'repository_id' => $repo1->id,
        ]);
        Finding::factory()->count(4)->create([
            'scan_id' => $scanRepo1->id,
            'severity' => FindingSeverity::HIGH,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        $scanRepo2 = $this->createScan($this->tenantA, [
            'name' => 'Scan Repo 2',
            'repository_id' => $repo2->id,
        ]);
        Finding::factory()->count(1)->create([
            'scan_id' => $scanRepo2->id,
            'severity' => FindingSeverity::LOW,
            'status' => FindingStatus::OPEN,
            'created_by' => $this->tenantA->id,
        ]);

        // Filter by Repo 1
        $resRepo1 = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats?repository_id=' . $repo1->id);
        $resRepo1->assertStatus(200)
            ->assertJsonCount(1, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scanRepo1->id)
            ->assertJsonPath('postureTrend.0.high', 4)
            ->assertJsonPath('postureTrend.0.low', 0);

        // Filter by Repo 2
        $resRepo2 = $this->actingAs($this->tenantA)->getJson('/api/dashboard/stats?repository_id=' . $repo2->id);
        $resRepo2->assertStatus(200)
            ->assertJsonCount(1, 'postureTrend')
            ->assertJsonPath('postureTrend.0.scan_id', $scanRepo2->id)
            ->assertJsonPath('postureTrend.0.high', 0)
            ->assertJsonPath('postureTrend.0.low', 1);
    }
}
