<?php

namespace Tests\Feature;

use App\DTOs\Import\NormalizedFinding;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\ScanInfrastructureJob;
use App\Jobs\ScanLocalJob;
use App\Jobs\ScanRepositoryJob;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\FindingIdentity;
use App\Models\LocalProject;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use App\Services\Import\FingerprintService;
use App\Services\Repository\RepositoryService;
use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\Services\Scan\Infrastructure\TargetValidator;
use App\Services\Scan\Infrastructure\ValidatedTarget;
use App\Services\Scan\RepositoryScanner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class FindingIdentityVerificationTest extends TestCase
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
            'email' => 'tenantA@trustnode.local',
            'name' => 'Tenant A Admin',
            'role_id' => $adminRole->id,
        ]);

        $this->tenantB = User::factory()->create([
            'email' => 'tenantB@trustnode.local',
            'name' => 'Tenant B Admin',
            'role_id' => $adminRole->id,
        ]);

        $this->repoA = Repository::factory()->create([
            'name' => 'org/repo-a',
            'repository_url' => 'https://github.com/org/repo-a',
            'created_by' => $this->tenantA->id,
        ]);

        $this->repoB = Repository::factory()->create([
            'name' => 'org/repo-b',
            'repository_url' => 'https://github.com/org/repo-b',
            'created_by' => $this->tenantA->id,
        ]);
    }

    private function createRepositoryScan(Repository $repo, User $user): Scan
    {
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $repo->id,
            'name' => 'Scan - '.$repo->name,
            'description' => 'Test scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $repo->name,
            'status' => ScanStatus::RUNNING,
            'progress' => 0,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Section 4: Cross-Scan Test
     * Scan A and Scan B on the same repository with same finding fingerprint:
     * - FindingIdentity count = 1
     * - Finding count = 2
     * - Both Finding records point to the same finding_identity_id
     * - Both retain different scan_id values
     */
    public function test_same_finding_across_two_scans_shares_single_finding_identity()
    {
        $mockFinding = new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => 'SEC-RULE-101',
            'title' => 'SQL Injection Vulnerability',
            'severity' => 'critical',
            'category' => 'SAST',
            'description' => 'SQLi in auth controller',
            'path' => 'app/Http/Controllers/Auth.php',
            'assetIdentifier' => $this->repoA->repository_url,
        ]);

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        // Scan 1
        $scan1 = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        // Scan 2
        $scan2 = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        // Authenticate as Tenant A to view scoped models
        $this->actingAs($this->tenantA);

        $identities = FindingIdentity::all();
        $this->assertCount(1, $identities, 'FindingIdentity count must be exactly 1 across repeated scans of same target');

        $findings = Finding::all();
        $this->assertCount(2, $findings, 'Finding count must be 2 representing two scan occurrences');

        $finding1 = Finding::where('scan_id', $scan1->id)->first();
        $finding2 = Finding::where('scan_id', $scan2->id)->first();

        $this->assertNotNull($finding1);
        $this->assertNotNull($finding2);
        $this->assertNotEquals($finding1->id, $finding2->id);
        $this->assertNotEquals($finding1->scan_id, $finding2->scan_id);

        $this->assertEquals($identities->first()->id, $finding1->finding_identity_id);
        $this->assertEquals($identities->first()->id, $finding2->finding_identity_id);
        $this->assertEquals($finding1->finding_identity_id, $finding2->finding_identity_id);
    }

    /**
     * Section 5: Different Target Test
     * Target A != Target B generates different FindingIdentity records
     */
    public function test_different_targets_generate_different_finding_identities()
    {
        $mockFinding = new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => 'SEC-RULE-COMMON',
            'title' => 'Common Vulnerability',
            'severity' => 'high',
            'category' => 'SAST',
            'path' => 'common.php',
        ]);

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        // Scan Repo A
        $scanA = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $jobA = new ScanRepositoryJob($scanA, $this->repoA);
        app()->call([$jobA, 'handle']);

        // Scan Repo B
        $scanB = $this->createRepositoryScan($this->repoB, $this->tenantA);
        $jobB = new ScanRepositoryJob($scanB, $this->repoB);
        app()->call([$jobB, 'handle']);

        $this->actingAs($this->tenantA);

        $identities = FindingIdentity::all();
        $this->assertCount(2, $identities, 'Different repositories must produce distinct FindingIdentity records');

        $findingA = Finding::where('scan_id', $scanA->id)->first();
        $findingB = Finding::where('scan_id', $scanB->id)->first();

        $this->assertNotEquals($findingA->finding_identity_id, $findingB->finding_identity_id);
    }

    /**
     * Section 6: Tenant Isolation Test
     * Tenant A and Tenant B scanning the same target/fingerprint:
     * - Generate distinct FindingIdentity records
     * - Tenant B cannot retrieve Tenant A's FindingIdentity
     */
    public function test_tenant_isolation_produces_separate_identities_and_prevents_cross_tenant_access()
    {
        $repoTenantB = Repository::factory()->create([
            'name' => 'org/repo-a',
            'repository_url' => 'https://github.com/org/repo-a',
            'created_by' => $this->tenantB->id,
        ]);

        $mockFinding = new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => 'SEC-RULE-TENANT',
            'title' => 'Tenant Isolated Finding',
            'severity' => 'medium',
            'category' => 'SAST',
            'path' => 'file.php',
        ]);

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        // Tenant A scan
        $scanA = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $jobA = new ScanRepositoryJob($scanA, $this->repoA);
        app()->call([$jobA, 'handle']);

        // Tenant B scan
        $scanB = $this->createRepositoryScan($repoTenantB, $this->tenantB);
        $jobB = new ScanRepositoryJob($scanB, $repoTenantB);
        app()->call([$jobB, 'handle']);

        // Query as Tenant A
        $this->actingAs($this->tenantA);
        $tenantAIdentities = FindingIdentity::all();
        $this->assertCount(1, $tenantAIdentities);
        $identityA = $tenantAIdentities->first();
        $this->assertEquals($this->tenantA->id, $identityA->created_by);

        // Query as Tenant B
        $this->actingAs($this->tenantB);
        $tenantBIdentities = FindingIdentity::all();
        $this->assertCount(1, $tenantBIdentities);
        $identityB = $tenantBIdentities->first();
        $this->assertEquals($this->tenantB->id, $identityB->created_by);

        // Verify IDs and hashes are distinct
        $this->assertNotEquals($identityA->id, $identityB->id);
        $this->assertNotEquals($identityA->identity_hash, $identityB->identity_hash);

        // Verify Tenant B cannot fetch Tenant A's identity by ID
        $found = FindingIdentity::find($identityA->id);
        $this->assertNull($found, 'Tenant B must not be able to retrieve Tenant A FindingIdentity');
    }

    /**
     * Section 7: Concurrency / Database Uniqueness Test
     * Verify database-level UNIQUE constraint prevents duplicate identity_hash inserts
     */
    public function test_database_unique_constraint_enforces_identity_hash_uniqueness()
    {
        $hash = hash('sha256', 'tenant-1|fingerprint-123|asset-1||');

        FindingIdentity::create([
            'identity_hash' => $hash,
            'fingerprint' => 'fingerprint-123',
            'asset_id' => null,
            'created_by' => $this->tenantA->id,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Direct DB duplicate insert must fail at database constraint level
        \Illuminate\Support\Facades\DB::table('finding_identities')->insert([
            'uuid' => (string) Str::uuid(),
            'identity_hash' => $hash,
            'fingerprint' => 'fingerprint-123',
            'created_by' => $this->tenantA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Section 8: Fingerprint Stability Test
     * Fingerprint does not depend on mutable fields (status, severity, description, remediation)
     */
    public function test_fingerprint_is_stable_across_mutable_triage_modifications()
    {
        $fingerprintService = new FingerprintService();

        $dto1 = new NormalizedFinding([
            'scanner' => 'repositoryscanner',
            'scannerRuleId' => 'RULE-STABLE',
            'title' => 'Original Title',
            'severity' => 'low',
            'description' => 'Original description',
            'remediation' => 'Original remediation',
            'path' => 'src/Core.php',
        ]);

        $dto2 = new NormalizedFinding([
            'scanner' => 'repositoryscanner',
            'scannerRuleId' => 'RULE-STABLE',
            'title' => 'Analyst Modified Title',
            'severity' => 'critical',
            'description' => 'Updated description with extra details',
            'remediation' => 'Alternative remediation steps',
            'path' => 'src/Core.php',
        ]);

        $fp1 = $fingerprintService->generate($dto1, 10);
        $fp2 = $fingerprintService->generate($dto2, 10);

        $this->assertEquals($fp1, $fp2, 'Fingerprint must remain identical despite changes to mutable triage fields');
    }

    /**
     * Section 9 & 10: Scan History and Failed Scan Safety
     * Failed scans do not create findings or update timestamps on existing identities
     */
    public function test_failed_scan_does_not_alter_existing_finding_identities()
    {
        $mockFinding = new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => 'SEC-RULE-FAILSAFE',
            'title' => 'Failsafe Test Finding',
            'severity' => 'medium',
            'category' => 'SAST',
            'path' => 'safe.php',
        ]);

        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')->andReturn([$mockFinding]);
        });

        // Scan 1 - Successful
        $scan1 = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $job1 = new ScanRepositoryJob($scan1, $this->repoA);
        app()->call([$job1, 'handle']);

        $this->actingAs($this->tenantA);
        $identity = FindingIdentity::first();
        $initialLastSeen = $identity->last_seen_at;

        // Scan 2 - Fails during checkout
        $this->mock(RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/workspace');
            $mock->shouldReceive('checkout')->andReturn(false); // Fails
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $scan2 = $this->createRepositoryScan($this->repoA, $this->tenantA);
        $job2 = new ScanRepositoryJob($scan2, $this->repoA);
        app()->call([$job2, 'handle']);

        $scan2->refresh();
        $this->assertEquals('failed', $scan2->status->value);

        // Verify Scan 2 produced 0 findings
        $this->assertEquals(0, $scan2->findings()->count());

        // Verify Scan 1 findings remain untouched
        $this->assertEquals(1, $scan1->findings()->count());

        // Verify identity last_seen_at was not modified by failed scan
        $identity->refresh();
        $this->assertEquals($initialLastSeen->toDateTimeString(), $identity->last_seen_at->toDateTimeString());
    }
}
