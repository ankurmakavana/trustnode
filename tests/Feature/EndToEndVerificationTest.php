<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AssetSeeder;
use Database\Seeders\RolePermissionSeeder;
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

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        // Seed Assets AFTER creating the user so the seeder finds a user and runs
        $this->seed(AssetSeeder::class);
    }

    /**
     * E2E Step 1: Root route returns 200 OK and HTML template.
     */
    public function test_root_route_returns_html_view()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('app.jsx'); // Vite stylesheet/JS indicators
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
                    'group', 'tags', 'created_by', 'updated_by', 'created_at', 'updated_at',
                ],
            ],
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
            'value' => '7', // 7 seeded assets in AssetSeeder
        ]);
    }
}
