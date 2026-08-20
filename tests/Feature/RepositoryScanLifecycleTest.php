<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\ScanRepositoryJob;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepositoryScanLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Repository $repository;

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
            'created_by' => $this->admin->id,
        ]);
    }

    private function createScan(ScanStatus $status, $updatedAt = null)
    {
        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $this->repository->id,
            'name' => 'Static Code Analysis - '.$this->repository->name,
            'description' => 'Vulnerability scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $this->repository->name,
            'status' => $status,
            'progress' => 0,
            'created_by' => $this->admin->id,
        ]);

        if ($updatedAt) {
            $scan->timestamps = false;
            $scan->updated_at = $updatedAt;
            $scan->save();
        }

        return $scan;
    }

    public function test_scan_can_start_when_no_active_scan_exists()
    {
        Queue::fake();

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(202);
        Queue::assertPushed(ScanRepositoryJob::class);
    }

    public function test_duplicate_scan_is_rejected_with_422_when_queued()
    {
        Queue::fake();
        $this->createScan(ScanStatus::QUEUED);

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(422)
            ->assertJson(['message' => 'A scan is already active for this repository.']);
        Queue::assertNotPushed(ScanRepositoryJob::class);
    }

    public function test_duplicate_scan_is_rejected_with_422_when_running()
    {
        Queue::fake();
        $this->createScan(ScanStatus::RUNNING);

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(422)
            ->assertJson(['message' => 'A scan is already active for this repository.']);
        Queue::assertNotPushed(ScanRepositoryJob::class);
    }

    public function test_completed_previous_scan_allows_new_scan()
    {
        Queue::fake();
        $this->createScan(ScanStatus::COMPLETED);

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(202);
        Queue::assertPushed(ScanRepositoryJob::class);
    }

    public function test_failed_previous_scan_allows_new_scan()
    {
        Queue::fake();
        $this->createScan(ScanStatus::FAILED);

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(202);
        Queue::assertPushed(ScanRepositoryJob::class);
    }

    public function test_cancelled_previous_scan_allows_new_scan()
    {
        Queue::fake();
        $this->createScan(ScanStatus::CANCELLED);

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(202);
        Queue::assertPushed(ScanRepositoryJob::class);
    }

    public function test_stale_abandoned_scan_does_not_permanently_block_repository()
    {
        Queue::fake();
        // Create a scan that has been running for 61 minutes (stale)
        $this->createScan(ScanStatus::RUNNING, now()->subMinutes(61));

        $response = $this->actingAs($this->admin)->postJson("/api/repositories/{$this->repository->id}/scan");

        $response->assertStatus(202);
        Queue::assertPushed(ScanRepositoryJob::class);

        // Ensure the stale scan was marked as failed
        $staleScan = Scan::where('status', ScanStatus::FAILED)->first();
        $this->assertNotNull($staleScan);
    }
}
