<?php

namespace Tests\Feature;

use App\DTOs\Import\NormalizedFinding;
use App\Enums\Finding\FindingLifecycleStatus;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\ScanInfrastructureJob;
use App\Jobs\ScanLocalJob;
use App\Jobs\ScanRepositoryJob;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\FindingLifecycleHistory;
use App\Models\LocalProject;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use App\Services\Finding\FindingLifecycleService;
use App\Services\Repository\RepositoryService;
use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\Services\Scan\Infrastructure\TargetValidator;
use App\Services\Scan\Infrastructure\ValidatedTarget;
use App\Services\Scan\RepositoryScanner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class FindingLifecycleIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantA;
    private User $tenantB;
    private Repository $repoA;
    private Repository $repoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->tenantA = User::factory()->create([
            'email' => 'tenantA_lifecycle@trustnode.local',
            'name' => 'Tenant A Lifecycle',
            'role_id' => $adminRole->id,
        ]);

        $this->tenantB = User::factory()->create([
            'email' => 'tenantB_lifecycle@trustnode.local',
            'name' => 'Tenant B Lifecycle',
            'role_id' => $adminRole->id,
        ]);

        $this->repoA = Repository::factory()->create([
            'name' => 'org/repo-lifecycle-a',
            'repository_url' => 'https://github.com/org/repo-lifecycle-a',
            'created_by' => $this->tenantA->id,
        ]);

        $this->repoB = Repository::factory()->create([
            'name' => 'org/repo-lifecycle-b',
            'repository_url' => 'https://github.com/org/repo-lifecycle-b',
            'created_by' => $this->tenantA->id,
        ]);
    }

    private function createScan(Repository $repo, User $user, int $timeOffsetMinutes = 0): Scan
    {
        $time = now()->addMinutes($timeOffsetMinutes);
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'name' => 'Scan - '.$repo->name,
            'description' => 'Test lifecycle scan',
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

    private function makeFinding(string $ruleId, string $title): NormalizedFinding
    {
        return new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => $ruleId,
            'title' => $title,
            'severity' => 'high',
            'category' => 'SAST',
            'path' => 'src/App.php',
            'assetIdentifier' => $this->repoA->repository_url,
        ]);
    }

    /**
     * 1. First occurrence -> NEW
     */
    public function test_first_scan_occurrence_is_classified_as_new()
    {
        $mockFinding = $this->makeFinding('SEC-101', 'SQL Injection');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        $scan = $this->createScan($this->repoA, $this->tenantA);
        $job = new ScanRepositoryJob($scan, $this->repoA);
        app()->call([$job, 'handle']);

        $this->actingAs($this->tenantA);

        $finding = Finding::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertEquals(FindingLifecycleStatus::NEW, $finding->lifecycle_status);

        $identity = FindingIdentity::find($finding->finding_identity_id);
        $this->assertEquals(FindingLifecycleStatus::NEW, $identity->lifecycle_status);
        $this->assertNotNull($identity->first_seen_at);
        $this->assertNotNull($identity->last_seen_at);
        $this->assertNull($identity->resolved_at);

        $history = FindingLifecycleHistory::where('finding_identity_id', $identity->id)->where('scan_id', $scan->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(FindingLifecycleStatus::NEW, $history->state);
    }

    /**
     * 2. Same finding in next successful scan -> RECURRING
     */
    public function test_subsequent_scan_occurrence_is_classified_as_recurring()
    {
        $mockFinding = $this->makeFinding('SEC-101', 'SQL Injection');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        // Scan 1 (T=0)
        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2 (T=10)
        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        $this->actingAs($this->tenantA);

        $finding1 = Finding::where('scan_id', $scan1->id)->first();
        $finding2 = Finding::where('scan_id', $scan2->id)->first();

        $this->assertEquals(FindingLifecycleStatus::NEW, $finding1->lifecycle_status);
        $this->assertEquals(FindingLifecycleStatus::RECURRING, $finding2->lifecycle_status);

        $identity = FindingIdentity::find($finding1->finding_identity_id);
        $this->assertEquals(FindingLifecycleStatus::RECURRING, $identity->lifecycle_status);
        $this->assertNull($identity->resolved_at);

        // Verify first_seen_at remains scan1's timestamp, and last_seen_at matches scan2
        $this->assertEquals($scan1->started_at->toDateTimeString(), $identity->first_seen_at->toDateTimeString());
        $this->assertEquals($scan2->started_at->toDateTimeString(), $identity->last_seen_at->toDateTimeString());
    }

    /**
     * 3. Disappearing finding in next successful scan -> RESOLVED
     *    and existing/remaining finding -> RECURRING
     */
    public function test_disappearing_finding_is_marked_resolved_without_creating_orphaned_row()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A');
        $findingB = $this->makeFinding('SEC-B', 'Vulnerability B');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        // Scan 1 finds both A and B
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA, $findingB) {
            $mock->shouldReceive('scan')->andReturn([$findingA, $findingB]);
        });

        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        $this->actingAs($this->tenantA);
        $finding1A = Finding::where('scan_id', $scan1->id)->where('title', 'Vulnerability A')->first();
        $finding1B = Finding::where('scan_id', $scan1->id)->where('title', 'Vulnerability B')->first();
        $identityA = FindingIdentity::find($finding1A->finding_identity_id);
        $identityB = FindingIdentity::find($finding1B->finding_identity_id);

        $this->assertEquals(FindingLifecycleStatus::NEW, $identityA->lifecycle_status);
        $this->assertEquals(FindingLifecycleStatus::NEW, $identityB->lifecycle_status);

        // Scan 2 finds ONLY B (A is fixed/disappeared)
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingB) {
            $mock->shouldReceive('scan')->andReturn([$findingB]);
        });

        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        // Assert that Scan 2 only has 1 Finding occurrence row (for B)
        $scan2Findings = Finding::where('scan_id', $scan2->id)->get();
        $this->assertCount(1, $scan2Findings);
        $this->assertEquals('Vulnerability B', $scan2Findings->first()->title);
        $this->assertEquals(FindingLifecycleStatus::RECURRING, $scan2Findings->first()->lifecycle_status);

        // Assert Identity A is RESOLVED
        $identityA->refresh();
        $this->assertEquals(FindingLifecycleStatus::RESOLVED, $identityA->lifecycle_status);
        $this->assertNotNull($identityA->resolved_at);
        $this->assertEquals($scan2->id, $identityA->resolved_by_scan_id);
        // last_seen_at must remain scan1's timestamp!
        $this->assertEquals($scan1->started_at->toDateTimeString(), $identityA->last_seen_at->toDateTimeString());

        // Assert Identity B is RECURRING
        $identityB->refresh();
        $this->assertEquals(FindingLifecycleStatus::RECURRING, $identityB->lifecycle_status);
        $this->assertNull($identityB->resolved_at);

        // Verify lifecycle history record for the resolved transition
        $resolvedHistory = FindingLifecycleHistory::where('finding_identity_id', $identityA->id)->where('scan_id', $scan2->id)->first();
        $this->assertNotNull($resolvedHistory);
        $this->assertEquals(FindingLifecycleStatus::RESOLVED, $resolvedHistory->state);
    }

    /**
     * 4. Resolved finding returns -> REGRESSION
     */
    public function test_resolved_finding_returning_in_later_scan_is_classified_as_regression()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        // Scan 1: Finding A present -> NEW
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });
        $scan1 = $this->createScan($this->repoA, $this->tenantA, 0);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2: Finding A absent -> RESOLVED
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) {
            $mock->shouldReceive('scan')->andReturn([]);
        });
        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        $this->actingAs($this->tenantA);
        $identity = FindingIdentity::first();
        $this->assertEquals(FindingLifecycleStatus::RESOLVED, $identity->lifecycle_status);

        // Scan 3: Finding A returns! -> REGRESSION
        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });
        $scan3 = $this->createScan($this->repoA, $this->tenantA, 20);
        $job3 = new ScanRepositoryJob($scan3, $this->repoA);
        app()->call([$job3, 'handle']);

        $finding3 = Finding::where('scan_id', $scan3->id)->first();
        $this->assertNotNull($finding3);
        $this->assertEquals(FindingLifecycleStatus::REGRESSION, $finding3->lifecycle_status);

        $identity->refresh();
        $this->assertEquals(FindingLifecycleStatus::REGRESSION, $identity->lifecycle_status);
        $this->assertNull($identity->resolved_at);
        $this->assertNull($identity->resolved_by_scan_id);
        $this->assertEquals($scan1->started_at->toDateTimeString(), $identity->first_seen_at->toDateTimeString());
        $this->assertEquals($scan3->started_at->toDateTimeString(), $identity->last_seen_at->toDateTimeString());
    }

    /**
     * 5 & 6. Failed scan does not resolve or cause regression
     */
    public function test_failed_scan_does_not_participate_in_lifecycle_transitions()
    {
        $findingA = $this->makeFinding('SEC-A', 'Vulnerability A');

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

        $this->actingAs($this->tenantA);
        $identity = FindingIdentity::first();
        $this->assertEquals(FindingLifecycleStatus::NEW, $identity->lifecycle_status);

        // Scan 2: Fails during checkout
        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(false); // Fail
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $scan2 = $this->createScan($this->repoA, $this->tenantA, 10);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        // Assert Identity is NOT marked resolved
        $identity->refresh();
        $this->assertEquals(FindingLifecycleStatus::NEW, $identity->lifecycle_status);
        $this->assertNull($identity->resolved_at);

        // Assert no lifecycle history record was created for Scan 2
        $scan2Histories = FindingLifecycleHistory::where('scan_id', $scan2->id)->get();
        $this->assertCount(0, $scan2Histories);
    }

    /**
     * 7, 8, 9, 10. Boundary Isolation: Different repository / target / local project / tenant do not correlate
     */
    public function test_target_boundary_and_tenant_isolation_in_lifecycle()
    {
        $finding = $this->makeFinding('SEC-ISOLATE', 'Isolate Test');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($finding) {
            $mock->shouldReceive('scan')->andReturn([$finding]);
        });

        // Scan Repo A for Tenant A
        $scanRepoA = $this->createScan($this->repoA, $this->tenantA, 0);
        $jobA = new ScanRepositoryJob($scanRepoA, $this->repoA);
        app()->call([$jobA, 'handle']);

        // Scan Repo B for Tenant A (same finding) -> Must be NEW, NOT RECURRING!
        $scanRepoB = $this->createScan($this->repoB, $this->tenantA, 10);
        $jobB = new ScanRepositoryJob($scanRepoB, $this->repoB);
        app()->call([$jobB, 'handle']);

        $this->actingAs($this->tenantA);

        $findingRepoA = Finding::where('scan_id', $scanRepoA->id)->first();
        $findingRepoB = Finding::where('scan_id', $scanRepoB->id)->first();

        $this->assertEquals(FindingLifecycleStatus::NEW, $findingRepoA->lifecycle_status);
        $this->assertEquals(FindingLifecycleStatus::NEW, $findingRepoB->lifecycle_status);
        $this->assertNotEquals($findingRepoA->finding_identity_id, $findingRepoB->finding_identity_id);
    }

    /**
     * 14. Repeated lifecycle processing is idempotent
     */
    public function test_lifecycle_processing_is_idempotent()
    {
        $findingA = $this->makeFinding('SEC-IDEMPOTENT', 'Idempotent Test');

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($findingA) {
            $mock->shouldReceive('scan')->andReturn([$findingA]);
        });

        $scan = $this->createScan($this->repoA, $this->tenantA, 0);
        $job = new ScanRepositoryJob($scan, $this->repoA);
        app()->call([$job, 'handle']);

        $service = app(FindingLifecycleService::class);

        // Run process a second time on the same scan
        $service->process($scan);

        $this->actingAs($this->tenantA);

        $histories = FindingLifecycleHistory::where('scan_id', $scan->id)->get();
        $this->assertCount(1, $histories, 'Idempotent execution must not create duplicate history records');

        $finding = Finding::where('scan_id', $scan->id)->first();
        $this->assertEquals(FindingLifecycleStatus::NEW, $finding->lifecycle_status);
    }
}
