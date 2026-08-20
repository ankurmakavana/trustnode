<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanFindingCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_findings_count_for_scans()
    {
        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $scanRunning = Scan::create([
            'name' => 'Running Scan',
            'type' => 'port_discovery',
            'engine' => 'nmap',
            'target' => '10.10.1.1',
            'status' => 'running',
            'progress' => 50,
            'created_by' => $user->id,
        ]);

        $scanCompleted = Scan::create([
            'name' => 'Completed Scan',
            'type' => 'port_discovery',
            'engine' => 'nmap',
            'target' => '10.10.1.2',
            'status' => 'completed',
            'progress' => 100,
            'created_by' => $user->id,
        ]);

        // Add 2 findings to the completed scan
        $scanCompleted->findings()->createMany([
            ['title' => 'Finding 1', 'severity' => 'high', 'category' => 'Network', 'description' => 'Test', 'created_by' => $user->id],
            ['title' => 'Finding 2', 'severity' => 'low', 'category' => 'Network', 'description' => 'Test', 'created_by' => $user->id],
        ]);

        $response = $this->actingAs($user)->getJson('/api/scans');
        $response->assertStatus(200);

        // Verify findings count in response
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
        
        foreach ($data as $item) {
            $this->assertArrayHasKey('findings_count', $item);
            if ($item['id'] === $scanRunning->id) {
                $this->assertEquals(0, $item['findings_count']);
            }
            if ($item['id'] === $scanCompleted->id) {
                $this->assertEquals(2, $item['findings_count']);
            }
        }
    }
}
