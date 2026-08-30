<?php

namespace Tests\Feature;

use App\DTOs\Import\NormalizedFinding;
use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\ScanRepositoryJob;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\FindingLifecycleHistory;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use App\Services\Repository\RepositoryService;
use App\Services\Scan\RepositoryScanner;
use App\Services\Scan\ScanBaselineComparisonService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class ScanBaselineRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantA;
    private User $tenantB;
    private Repository $repoA;
    private Repository $repoB;
    private ScanBaselineComparisonService $comparisonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->tenantA = User::factory()->create([
            'email' => 'tenantA_baseline@trustnode.local',
            'name' => 'Tenant A Baseline',
            'role_id' => $adminRole->id,
        ]);

        $this->tenantB = User::factory()->create([
            'email' => 'tenantB_baseline@trustnode.local',
            'name' => 'Tenant B Baseline',
            'role_id' => $adminRole->id,
        ]);

        $this->repoA = Repository::factory()->create([
            'name' => 'org/repo-baseline-a',
            'repository_url' => 'https://github.com/org/repo-baseline-a',
            'created_by' => $this->tenantA->id,
        ]);

        $this->repoB = Repository::factory()->create([
            'name' => 'org/repo-baseline-b',
            'repository_url' => 'https://github.com/org/repo-baseline-b',
            'created_by' => $this->tenantA->id,
        ]);

        $this->comparisonService = app(ScanBaselineComparisonService::class);
    }

    private function createScan(Repository $repo, User $user, int $timeOffsetMinutes = 0): Scan
    {
        $time = now()->addMinutes($timeOffsetMinutes);
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'name' => 'Scan - '.$repo->name,
            'description' => 'Test baseline scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $repo->name,
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'started_at' => $time,
            'created_at' => $time,
            'created_by' => $user->id,
        ]);
    }

    private function makeFinding(string $ruleId, string $title, string $severity = 'high'): NormalizedFinding
    {
        return new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => $ruleId,
            'title' => $title,
            'severity' => $severity,
            'category' => 'SAST',
            'path' => 'src/App.php',
            'assetIdentifier' => $this->repoA->repository_url,
        ]);
    }

    /**
     * 1. First successful scan establishes initial baseline semantics with no previous scan.
     */
    public function test_first_successful_scan_is_initial_baseline_with_no_previous_scan()
    {
        $finding = $this->makeFinding('SEC-1', 'Initial SQLi', 'critical');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($finding) {
            $mock->shouldReceive('scan')->andReturn([$finding]);
        });

        $scan = $this->createScan($this->repoA, $this->tenantA, 0);
        $job = new ScanRepositoryJob($scan, $this->repoA);
        app()->call([$job, 'handle']);

        $this->actingAs($this->tenantA);

        $delta = $this->comparisonService->compare($scan);

        $this->assertTrue($delta['is_initial_baseline']);
        $this->assertNull($delta['previous_scan']);
        $this->assertEquals($scan->id, $delta['initial_baseline']['id']);
        $this->assertEquals(1, $delta['lifecycle_counts']['new']);
        $this->assertEquals(0, $delta['lifecycle_counts']['recurring']);
        $this->assertEquals(0, $delta['lifecycle_counts']['resolved']);
        $this->assertEquals(0, $delta['lifecycle_counts']['regression']);
        $this->assertEquals(0, $delta['posture']['previous_open_count']);
        $this->assertEquals(1, $delta['posture']['current_open_count']);
        $this->assertEquals(1, $delta['posture']['net_change']);
        $this->assertEquals('initial_baseline', $delta['posture']['assessment']);
        $this->assertEquals(1, $delta['severity_delta']['critical']['current']);
        $this->assertEquals(0, $delta['severity_delta']['critical']['previous']);
        $this->assertEquals(1, $delta['severity_delta']['critical']['delta']);
    }

    /**
     * 2. Second successful scan computes delta, severity changes, and regressions accurately.
     */
    public function test_second_scan_computes_delta_severity_and_regressions()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A', 'critical');
        $findingB = $this->makeFinding('SEC-B', 'Vulnerability B', 'high');
        $findingC = $this->makeFinding('SEC-C', 'Vulnerability C', 'medium');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        // Scan 1: A (critical) and B (high)
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA, $findingB) {
            $mock->shouldReceive('scan')->andReturn([$findingA, $findingB]);
        });
        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2: B (high) and C (medium). (A is resolved, B is recurring, C is new)
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingB, $findingC) {
            $mock->shouldReceive('scan')->andReturn([$findingB, $findingC]);
        });
        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        $this->actingAs($this->tenantA);

        $delta = $this->comparisonService->compare($scan2);

        $this->assertFalse($delta['is_initial_baseline']);
        $this->assertEquals($scan1->id, $delta['previous_scan']['id']);
        $this->assertEquals($scan1->id, $delta['initial_baseline']['id']);
        $this->assertEquals(1, $delta['lifecycle_counts']['new']); // C
        $this->assertEquals(1, $delta['lifecycle_counts']['recurring']); // B
        $this->assertEquals(1, $delta['lifecycle_counts']['resolved']); // A
        $this->assertEquals(0, $delta['lifecycle_counts']['regression']);
        $this->assertEquals(2, $delta['posture']['previous_open_count']);
        $this->assertEquals(2, $delta['posture']['current_open_count']);
        $this->assertEquals(0, $delta['posture']['net_change']);
        $this->assertEquals('unchanged', $delta['posture']['assessment']);

        // Severity delta
        $this->assertEquals(-1, $delta['severity_delta']['critical']['delta']); // 1 -> 0
        $this->assertEquals(0, $delta['severity_delta']['high']['delta']); // 1 -> 1
        $this->assertEquals(1, $delta['severity_delta']['medium']['delta']); // 0 -> 1
    }

    /**
     * 3. Zero-finding scan resolves active findings and reports 'improving' posture.
     */
    public function test_zero_finding_scan_resolves_all_and_reports_improving_posture()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A', 'critical');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        // Scan 1: A
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });
        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2: Zero findings (clean)
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) {
            $mock->shouldReceive('scan')->andReturn([]);
        });
        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        $this->actingAs($this->tenantA);

        $delta = $this->comparisonService->compare($scan2);

        $this->assertEquals(1, $delta['lifecycle_counts']['resolved']);
        $this->assertEquals(0, $delta['lifecycle_counts']['new']);
        $this->assertEquals(0, $delta['lifecycle_counts']['recurring']);
        $this->assertEquals(1, $delta['posture']['previous_open_count']);
        $this->assertEquals(0, $delta['posture']['current_open_count']);
        $this->assertEquals(-1, $delta['posture']['net_change']);
        $this->assertEquals('improving', $delta['posture']['assessment']);
    }

    /**
     * 4. Failed scan between two successful scans is ignored by comparison.
     */
    public function test_failed_scan_between_successful_scans_is_ignored_by_comparison()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A', 'high');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        // Scan 1: Successful
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });
        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2: Fails during checkout
        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(false);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });
        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        // Scan 3: Successful (same finding A)
        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });
        $scan3 = $this->createScan($this->repoA, $this->tenantA, 20);
        $job3 = new ScanRepositoryJob($scan3, $this->repoA);
        app()->call([$job3, 'handle']);

        $this->actingAs($this->tenantA);

        // Scan 3 should compare directly against Scan 1 (ignoring failed Scan 2)
        $delta = $this->comparisonService->compare($scan3);

        $this->assertEquals($scan1->id, $delta['previous_scan']['id']);
        $this->assertEquals(1, $delta['lifecycle_counts']['recurring']);
        $this->assertEquals(0, $delta['lifecycle_counts']['resolved']);
    }

    /**
     * 5. API endpoint GET /api/scans/{scan}/lifecycle returns correct comparison and enforces auth.
     */
    public function test_api_scans_lifecycle_endpoint_behavior_and_isolation()
    {
        $finding = $this->makeFinding('SEC-API', 'API Test Vuln', 'critical');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($finding) {
            $mock->shouldReceive('scan')->andReturn([$finding]);
        });

        $scan = $this->createScan($this->repoA, $this->tenantA, 0);
        $job = new ScanRepositoryJob($scan, $this->repoA);
        app()->call([$job, 'handle']);

        // Unauthenticated request -> 401
        $response = $this->getJson("/api/scans/{$scan->id}/lifecycle");
        $response->assertStatus(401);

        // Tenant A request -> 200 with JSON payload
        $this->actingAs($this->tenantA);
        $responseA = $this->getJson("/api/scans/{$scan->id}/lifecycle");
        $responseA->assertStatus(200)
            ->assertJsonStructure([
                'current_scan',
                'previous_scan',
                'initial_baseline',
                'is_initial_baseline',
                'lifecycle_counts' => ['new', 'recurring', 'resolved', 'regression'],
                'posture' => ['previous_open_count', 'current_open_count', 'net_change', 'assessment'],
                'severity_delta',
                'regressions',
            ]);

        // Tenant B request (IDOR attempt) -> 404
        $this->actingAs($this->tenantB);
        $responseB = $this->getJson("/api/scans/{$scan->id}/lifecycle");
        $responseB->assertStatus(404);
    }
}
