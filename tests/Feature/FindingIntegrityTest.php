<?php

namespace Tests\Feature;

use App\DTOs\Import\NormalizedFinding;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\ScanRepositoryJob;
use App\Models\Asset;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use App\Services\Scan\RepositoryScanner;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Mockery\MockInterface;

class FindingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Repository $repository;
    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->admin = User::factory()->create([
            'email' => 'admin@trustnode.local',
            'name' => 'TrustNode Admin',
            'role_id' => $adminRole->id,
        ]);

        $this->repository = Repository::factory()->create([
            'name' => 'test/repo',
            'repository_url' => 'https://github.com/test/repo',
        ]);

        $this->asset = Asset::create([
            'name' => 'https://github.com/test/repo',
            'value' => 'https://github.com/test/repo',
            'type' => \App\Enums\Asset\AssetType::DOMAIN,
            'criticality' => \App\Enums\Asset\AssetCriticality::MEDIUM,
            'status' => \App\Enums\Asset\AssetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);
    }

    private function createScan(): Scan
    {
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $this->repository->id,
            'name' => 'Static Code Analysis - '.$this->repository->name,
            'description' => 'Vulnerability scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $this->repository->name,
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_findings_are_isolated_per_scan_and_not_overwritten()
    {
        // Mock the RepositoryScanner to always return the exact same vulnerability
        $mockFinding = new NormalizedFinding([
            'scanner' => 'RepositoryScanner',
            'scannerRuleId' => 'SEC-MOCK-1',
            'title' => 'Mock Vulnerability',
            'severity' => 'high',
            'category' => 'SAST',
            'description' => 'Mock desc',
            'remediation' => 'Mock remediation',
            'technicalDetails' => 'Mock details',
            'evidence' => 'Mock evidence',
            'url' => 'http://example.com',
            'path' => 'src/Mock.php',
            'assetIdentifier' => $this->repository->repository_url,
        ]);

        $this->mock(\App\Services\Repository\RepositoryService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createTemporaryWorkspace')->andReturn('/tmp/mocked-path');
            $mock->shouldReceive('cloneRepository')->andReturn(true);
            $mock->shouldReceive('checkout')->andReturn(true);
            $mock->shouldReceive('cleanWorkspace')->andReturn(true);
        });

        $this->mock(RepositoryScanner::class, function (MockInterface $mock) use ($mockFinding) {
            $mock->shouldReceive('scan')
                ->andReturn([$mockFinding]);
        });

        // Run scan 1
        $scan1 = $this->createScan();
        $scan1->status = ScanStatus::RUNNING;
        $scan1->save();

        $job1 = new ScanRepositoryJob($scan1, $this->repository);
        app()->call([$job1, 'handle']);

        $scan1->refresh();
        $this->assertEquals(1, $scan1->findings()->count());
        $this->assertDatabaseHas('findings', [
            'scan_id' => $scan1->id,
            'title' => 'Mock Vulnerability'
        ]);

        // Run scan 2
        $scan2 = $this->createScan();
        $scan2->status = ScanStatus::RUNNING;
        $scan2->save();

        $job2 = new ScanRepositoryJob($scan2, $this->repository);
        app()->call([$job2, 'handle']);

        $this->assertEquals(1, $scan2->findings()->count());
        
        // Assert that BOTH findings exist independently in the database
        // (Previously, scan 2 would overwrite scan 1's finding due to asset_id dedup)
        $this->assertEquals(2, \App\Models\Finding::where('title', 'Mock Vulnerability')->count());
        
        // Ensure historical scan evidence remained traceable
        $this->assertDatabaseHas('findings', [
            'scan_id' => $scan1->id,
            'title' => 'Mock Vulnerability'
        ]);
        
        $this->assertDatabaseHas('findings', [
            'scan_id' => $scan2->id,
            'title' => 'Mock Vulnerability'
        ]);
    }
}
