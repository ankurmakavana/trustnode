<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Scan;
use App\Models\Finding;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private Repository $repoA;

    protected function setUp(): void
    {
        parent::setUp();
        
        $role = \App\Models\Role::firstOrCreate(['name' => 'User', 'slug' => 'user']);
        
        $this->userA = User::factory()->create(['role_id' => $role->id, 'status' => \App\Enums\UserStatus::ACTIVE]);
        $this->userB = User::factory()->create(['role_id' => $role->id, 'status' => \App\Enums\UserStatus::ACTIVE]);
        
        $this->repoA = Repository::factory()->create(['created_by' => $this->userA->id]);
    }

    public function test_token_creation_and_secret_not_returned_after(): void
    {
        $response = $this->actingAs($this->userA)
            ->postJson('/api/auth/tokens', [
                'name' => 'Test Token',
                'abilities' => ['scans:read'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'message']);
            
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        
        // Ensure token is not returned from /api/auth/me
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');
            
        $meResponse->assertStatus(200);
        $meResponse->assertJsonMissing(['token' => $token, 'password' => '']);
    }

    public function test_authenticated_request(): void
    {
        Sanctum::actingAs($this->userA, ['scans:read']);
        
        $this->getJson('/api/auth/me')->assertStatus(200)->assertJsonPath('data.uuid', $this->userA->uuid);
    }

    public function test_unauthenticated_request_rejection(): void
    {
        $this->postJson('/api/auth/tokens')->assertStatus(401);
    }

    public function test_invalid_token_rejection(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJson(['authenticated' => false]);
    }

    public function test_token_revocation_deletes_token(): void
    {
        $token = $this->userA->createToken('test')->plainTextToken;
        
        $this->userA->status = \App\Enums\UserStatus::ACTIVE;
        $this->userA->save();
            
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/auth/tokens/' . $this->userA->tokens()->first()->id)
            ->assertStatus(200);
            
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_ability_enforcement(): void
    {
        // scans:read only
        Sanctum::actingAs($this->userA, ['scans:read']);
        
        // Use a 404 or 422 to prove we got PAST the 403 Ability middleware
        // POST /api/scans requires scans:write, should return 403 from CheckTokenAbilities
        $this->postJson('/api/scans', ['repository_id' => $this->repoA->id])->assertStatus(403);
        
        // scans:write only
        Sanctum::actingAs($this->userA, ['scans:write']);
        // Since we pass the ability check, we should get 422 (validation) instead of 403
        $this->postJson('/api/scans', [])->assertStatus(422);
    }
    
    public function test_tenant_isolation_and_policy_enforcement(): void
    {
        Sanctum::actingAs($this->userB, ['scans:read', 'scans:write', 'findings:read']);
        
        $scan = new Scan([
            'repository_id' => $this->repoA->id,
            'created_by' => $this->userA->id,
            'status' => \App\Enums\Scan\ScanStatus::QUEUED,
            'name' => 'Test Scan',
            'type' => \App\Enums\Scan\ScanType::REPOSITORY,
            'engine' => \App\Enums\Scan\ScanEngine::REPOSITORY_SCANNER,
            'target' => 'https://github.com',
        ]);
        $scan->save();
        
        // User B should get 403 or 404 when trying to access User A's scan
        $response = $this->getJson('/api/scans/' . $scan->id);
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    public function test_rate_limiting(): void
    {
        // This is tricky to test cleanly without mocking, but we can verify the limit exists
        $this->markTestIncomplete('Rate limiting tested manually / configured via throttle middleware');
    }
}
