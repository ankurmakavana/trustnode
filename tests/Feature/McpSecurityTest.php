<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_token_with_read_abilities_cannot_modify_resources()
    {
        // 1. Create a user
        $user = User::factory()->create();

        // 2. Authenticate the user with only read abilities (as Claude MCP would use)
        Sanctum::actingAs(
            $user,
            ['scans:read', 'findings:read', 'reports:read']
        );

        // 3. Attempt to fetch a resource (Should work if they own it, but wait, we need to create it first)
        // We'll just test that POST/PUT/DELETE fail due to missing ability.

        // Attempt to create a scan
        $response = $this->postJson('/api/scans', [
            'name' => 'Malicious Scan',
            'type' => 'network_ip',
            'engine' => 'nmap',
            'target' => '127.0.0.1',
        ]);

        // Should be forbidden because they lack scans:write
        $response->assertStatus(403);
    }
    
    public function test_mcp_token_cross_tenant_isolation()
    {
        // User A creates a scan
        $userA = User::factory()->create();
        $repoA = \App\Models\Repository::factory()->create();
        
        $scanA = \App\Models\Scan::factory()->create([
            'created_by' => $userA->id,
            'repository_id' => $repoA->id,
            'type' => \App\Enums\Scan\ScanType::REPOSITORY,
            'engine' => \App\Enums\Scan\ScanEngine::REPOSITORY_SCANNER,
            'target' => 'https://github.com/test'
        ]);

        // User B authenticates via MCP token
        $userB = User::factory()->create();
        Sanctum::actingAs(
            $userB,
            ['scans:read', 'findings:read', 'reports:read']
        );

        // User B tries to read User A's scan
        $response = $this->getJson("/api/scans/{$scanA->id}");

        // Backend tenant isolation should reject it (404 or 403)
        $response->assertStatus(404);
    }
}
