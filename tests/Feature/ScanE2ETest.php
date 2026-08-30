<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Models\Scan;
use App\Models\User;
use App\Jobs\ScanInfrastructureJob;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScanE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_e2e_infrastructure_scan()
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'administrator')->first()->id]);

        Queue::fake();

        $payload = [
            'name' => 'E2E Target Scan',
            'type' => ScanType::NETWORK_IP->value,
            'engine' => ScanEngine::NMAP->value,
            'target' => 'example.com',
            'status' => 'queued',
            'progress' => 0,
        ];

        // 1. Create scan through API
        $response = $this->actingAs($admin)->postJson('/api/scans', $payload);
        $response->assertStatus(201);
        $scanId = $response->json('data.id');

        $scan = Scan::find($scanId);
        $this->assertNotNull($scan);
        $this->assertEquals(ScanType::NETWORK_IP, $scan->type);

        // 2. Verify job was dispatched
        Queue::assertPushed(ScanInfrastructureJob::class);

        // 3. Process the job manually with a mock scanner to avoid scanning example.com
        $mockScanner = $this->getMockBuilder(\App\Services\Scan\Infrastructure\NativeInfrastructureScanner::class)
            ->onlyMethods(['scan'])
            ->getMock();
            
        $mockScanner->expects($this->once())
            ->method('scan')
            ->willReturn([
                new \App\DTOs\Import\NormalizedFinding([
                    'scanner' => 'NativeInfrastructureScanner',
                    'title' => 'Open Ports Detected',
                    'severity' => 'info',
                    'url' => 'example.com'
                ])
            ]);
            
        $this->app->instance(\App\Services\Scan\Infrastructure\NativeInfrastructureScanner::class, $mockScanner);

        $job = new ScanInfrastructureJob($scan);
        app()->call([$job, 'handle']);

        // 4. Verify scan was completed and findings generated
        $scan->refresh();
        if ($scan->status !== ScanStatus::COMPLETED) {
            dump($scan->error_message);
        }
        $this->assertEquals(ScanStatus::COMPLETED, $scan->status);
        $this->assertEquals(100, $scan->progress);
        $this->assertTrue($scan->findings()->count() > 0);
    }
    public function test_infrastructure_scan_tenant_isolation()
    {
        $this->seed(RolePermissionSeeder::class);
        $roleId = \App\Models\Role::where('slug', 'administrator')->first()->id;
        
        $userA = User::factory()->create(['role_id' => $roleId]);
        $userB = User::factory()->create(['role_id' => $roleId]);
        
        // User A creates a scan
        $payload = [
            'name' => 'User A Target Scan',
            'type' => ScanType::NETWORK_IP->value,
            'engine' => ScanEngine::NMAP->value,
            'target' => 'example.com',
            'status' => 'queued',
        ];
        
        $response = $this->actingAs($userA)->postJson('/api/scans', $payload);
        $response->assertStatus(201);
        $scanId = $response->json('data.id');
        
        // User B should NOT be able to view it
        $this->actingAs($userB)->getJson("/api/scans/{$scanId}")->assertStatus(404);
        
        // User B should NOT be able to delete it
        $this->actingAs($userB)->deleteJson("/api/scans/{$scanId}")->assertStatus(404);
    }
}
