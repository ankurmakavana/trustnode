<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EndToEndVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->seed(\Database\Seeders\AssetSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);
    }

    /**
     * E2E Step 1: Root route establishes a session and returns 200 OK.
     */
    public function test_root_route_auto_logs_in_user_and_returns_html_view()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('app-'); // Vite stylesheet/JS indicators
        $this->assertAuthenticated();
    }

    /**
     * E2E Step 2: GET /api/assets with the authenticated session returns 200 OK and JSON.
     */
    public function test_assets_endpoint_returns_json_catalog_for_session()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/assets');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'uuid', 'name', 'type', 'value', 'description', 
                    'criticality', 'status', 'risk_score', 'owner', 'notes', 
                    'group', 'tags', 'created_by', 'updated_by', 'created_at', 'updated_at'
                ]
            ]
        ]);
        
        // Assert we got the seeded data (e.g. auth.internal)
        $response->assertJsonFragment(['value' => 'auth.internal']);
    }

    /**
     * E2E Step 3: GET /api/dashboard/stats returns 200 OK and matches DB state.
     */
    public function test_dashboard_stats_endpoint_returns_db_counts()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/dashboard/stats');
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => 'assets',
            'value' => '7' // 7 seeded assets in AssetSeeder
        ]);
    }
}
