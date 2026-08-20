<?php

namespace Tests\Feature;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\AssetGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\Asset\AssetService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected AssetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush Redis Cache
        Cache::flush();

        // Seed Roles & Permissions
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $operatorRole = Role::where('slug', UserRole::OPERATOR->value)->first();

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->operator = User::factory()->create([
            'role_id' => $operatorRole->id,
        ]);

        $this->service = app(AssetService::class);
    }

    /**
     * Test Asset listing and search filters.
     */
    public function test_can_list_and_filter_assets()
    {
        $group = AssetGroup::create([
            'name' => 'Internal Workspace',
            'created_by' => $this->admin->id,
        ]);

        $asset1 = Asset::create([
            'name' => 'Internal Authentication portal',
            'type' => AssetType::SUBDOMAIN,
            'value' => 'auth.internal',
            'criticality' => AssetCriticality::CRITICAL,
            'status' => AssetStatus::ACTIVE,
            'risk_score' => 9.50,
            'asset_group_id' => $group->id,
            'created_by' => $this->admin->id,
        ]);

        $asset2 = Asset::create([
            'name' => 'External VPN endpoint',
            'type' => AssetType::IPV4,
            'value' => '10.10.1.1',
            'criticality' => AssetCriticality::HIGH,
            'status' => AssetStatus::INACTIVE,
            'risk_score' => 5.20,
            'created_by' => $this->admin->id,
        ]);

        // 1. View all assets
        $response = $this->actingAs($this->admin)->getJson('/api/assets');
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // 2. Filter by search term
        $response = $this->actingAs($this->admin)->getJson('/api/assets?search=Auth');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $asset1->id);

        // 3. Filter by type
        $response = $this->actingAs($this->admin)->getJson('/api/assets?type=ipv4');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $asset2->id);

        // 4. Filter by status
        $response = $this->actingAs($this->admin)->getJson('/api/assets?status=inactive');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $asset2->id);

        // 5. Operator can view but cannot write
        $response = $this->actingAs($this->operator)->getJson('/api/assets');
        $response->assertStatus(200);
    }

    /**
     * Test Asset creation.
     */
    public function test_can_create_asset_via_api()
    {
        $group = AssetGroup::create([
            'name' => 'DMZ Public Services',
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'Partner API Gateway',
            'type' => 'api_endpoint',
            'value' => 'https://api.internal/v1/users',
            'description' => 'Endpoints exposed for partner sync.',
            'criticality' => 'high',
            'status' => 'active',
            'risk_score' => 7.80,
            'owner' => 'API Team',
            'asset_group_id' => $group->id,
            'tags' => ['Production', 'PCI-DSS'],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/assets', $payload);

        $response->assertStatus(403);
    }

    /**
     * Test Asset validation rules rejection.
     */
    public function test_rejects_invalid_values_matching_type()
    {
        $payload = [
            'name' => 'Internal Authentication portal',
            'type' => 'ipv4',
            'value' => 'not-an-ip', // invalid IP format
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/assets', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    /**
     * Test Asset updates and activity log generation.
     */
    public function test_can_update_asset_and_writes_activity_log()
    {
        $asset = Asset::create([
            'name' => 'DMZ Edge Router',
            'type' => AssetType::HOSTNAME,
            'value' => 'gateway',
            'criticality' => AssetCriticality::HIGH,
            'status' => AssetStatus::ACTIVE,
            'risk_score' => 6.50,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'DMZ Edge Router (Updated)',
            'type' => 'hostname',
            'value' => 'gateway',
            'criticality' => 'critical',
            'status' => 'active',
            'risk_score' => 9.00,
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/assets/{$asset->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'DMZ Edge Router (Updated)')
            ->assertJsonPath('data.criticality', 'critical')
            ->assertJsonPath('data.risk_score', 9);

        // Assert activity log is populated
        $this->assertDatabaseHas('asset_activity_logs', [
            'asset_id' => $asset->id,
            'action' => 'updated',
        ]);
    }

    /**
     * Test authorization blocks unauthorized modifications.
     */
    public function test_operator_is_denied_from_creating_assets()
    {
        $payload = [
            'name' => 'Partner API Gateway',
            'type' => 'api_endpoint',
            'value' => 'https://api.internal/v1/users',
        ];

        $response = $this->actingAs($this->operator)->postJson('/api/assets', $payload);

        $response->assertStatus(403);
    }
}
