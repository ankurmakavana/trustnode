<?php

namespace Tests\Feature;

use App\Enums\Asset\AssetType;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
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
    }

    /**
     * Test dashboard stats endpoint retrieves real database asset counts.
     */
    public function test_dashboard_stats_endpoint_returns_real_counts()
    {
        // Create some assets
        Asset::create([
            'name' => 'Internal Server 1',
            'type' => AssetType::HOSTNAME,
            'value' => 'gateway.internal',
            'created_by' => $this->admin->id,
        ]);

        Asset::create([
            'name' => 'Internal Server 2',
            'type' => AssetType::HOSTNAME,
            'value' => 'portal.internal',
            'created_by' => $this->admin->id,
        ]);

        // Create some targets
        Target::create([
            'name' => 'Production Endpoint',
            'type' => TargetType::DOMAIN,
            'value' => 'api.internal',
            'environment' => TargetEnvironment::PRODUCTION,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonPath('stats.0.id', 'assets')
            ->assertJsonPath('stats.0.value', '2')
            ->assertJsonPath('stats.0.delta', '+2')
            ->assertJsonPath('stats.1.id', 'targets')
            ->assertJsonPath('stats.1.value', '1')
            ->assertJsonPath('stats.1.delta', '+1');
    }
}
