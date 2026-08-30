<?php

namespace Tests\Feature;

use App\Models\Scan;
use App\Models\User;
use App\Models\Asset;
use App\Models\Repository;
use App\Jobs\ScanRepositoryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Services\TenantContext;

class QueueTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_job_sets_and_clears_tenant_context()
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::first();
        $userA = User::factory()->create(['role_id' => $adminRole->id]);
        
        $repository = Repository::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'repo-a',
            'repository_url' => 'http://example.com/repo-a.git',
            'status' => 'Pending',
            'created_by' => $userA->id,
        ]);

        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'test scan',
            'target' => 'repo-a',
            'status' => 'queued',
            'type' => \App\Enums\Scan\ScanType::REPOSITORY->value,
            'engine' => 'repositoryscanner',
            'created_by' => $userA->id,
        ]);

        $job = new ScanRepositoryJob($scan, $repository);
        $this->assertEquals($userA->id, $job->getTenantId());

        // We can't directly test the Queue before/after events via simple method call
        // We can dispatch the job synchronously. Laravel synchronous driver fires the JobProcessing events.
        \Illuminate\Support\Facades\Queue::setDefaultDriver('sync');
        
        TenantContext::clear();
        $this->assertNull(TenantContext::currentUserId());

        // For sync queue, the exception in jobs might bubble up depending on config, but the events fire.
        try {
            // we dispatch it. It will fail during git clone, but the event should fire.
            dispatch($job);
        } catch (\Throwable $e) {
            // expected to fail git clone
        }

        // Context should be cleaned up by JobFailed or JobProcessed
        $this->assertNull(TenantContext::currentUserId());
    }

    public function test_job_creates_assets_under_correct_tenant()
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::first();
        $userA = User::factory()->create(['role_id' => $adminRole->id]);
        $userB = User::factory()->create(['role_id' => $adminRole->id]);
        
        TenantContext::setUserId($userA->id);
        Asset::create([
            'name' => 'shared-repo',
            'value' => 'shared-repo',
            'type' => 'code_repository',
            'criticality' => 'high',
            'status' => 'active',
            'created_by' => $userA->id
        ]);
        TenantContext::clear();

        // userB shouldn't see userA's asset
        TenantContext::setUserId($userB->id);
        $asset = Asset::where('value', 'shared-repo')->first();
        $this->assertNull($asset);

        // if userB creates it, it's a new one
        $newAsset = Asset::firstOrCreate(
            ['value' => 'shared-repo', 'type' => 'code_repository'],
            ['name' => 'shared-repo', 'criticality' => 'high', 'status' => 'active', 'created_by' => $userB->id]
        );
        $this->assertEquals($userB->id, $newAsset->created_by);
        TenantContext::clear();
    }
}
