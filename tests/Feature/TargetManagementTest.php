<?php

namespace Tests\Feature;

use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TargetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $operatorRole = Role::where('slug', UserRole::OPERATOR->value)->first();
        $this->operator = User::factory()->create([
            'role_id' => $operatorRole->id,
        ]);
    }

    /**
     * Test paginated targets index with searches.
     */
    public function test_can_list_and_filter_targets()
    {
        Target::create([
            'name' => 'Internal VPN Firewall',
            'type' => TargetType::IP_ADDRESS,
            'value' => '10.10.1.1',
            'environment' => TargetEnvironment::INTERNAL,
            'criticality' => TargetCriticality::CRITICAL,
            'status' => TargetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        Target::create([
            'name' => 'Main Public Server',
            'type' => TargetType::DOMAIN,
            'value' => 'portal.internal',
            'environment' => TargetEnvironment::PRODUCTION,
            'criticality' => TargetCriticality::MEDIUM,
            'status' => TargetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/targets?search=VPN');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Internal VPN Firewall');
    }

    /**
     * Test target creation validations.
     */
    public function test_can_create_target_via_api()
    {
        $payload = [
            'name' => 'Partner API Gateway Server',
            'type' => 'api_endpoint',
            'value' => 'https://api.internal/v1/users',
            'environment' => 'production',
            'criticality' => 'high',
            'status' => 'active',
            'description' => 'Target descriptions',
            'tags' => ['API', 'Partners'],
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/targets', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Partner API Gateway Server');

        $this->assertDatabaseHas('targets', [
            'value' => 'https://api.internal/v1/users',
        ]);
    }

    /**
     * Test update operations logs activity history correctly.
     */
    public function test_can_update_target_and_writes_activity_log()
    {
        $target = Target::create([
            'name' => 'Corporate Main Server',
            'type' => TargetType::DOMAIN,
            'value' => 'corp.internal',
            'environment' => TargetEnvironment::PRODUCTION,
            'criticality' => TargetCriticality::MEDIUM,
            'status' => TargetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'Corporate Main Server Updated',
            'type' => 'domain',
            'value' => 'corp.internal',
            'environment' => 'production',
            'criticality' => 'high',
            'status' => 'inactive',
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/targets/{$target->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Corporate Main Server Updated')
            ->assertJsonPath('data.criticality', 'high');

        $this->assertDatabaseHas('target_activity_logs', [
            'target_id' => $target->id,
            'action' => 'updated',
        ]);
    }

    /**
     * Test operators are blocked from registering targets.
     */
    public function test_operator_is_denied_from_creating_targets()
    {
        $payload = [
            'name' => 'Secured Gateway Node',
            'type' => 'ip_address',
            'value' => '192.168.1.1',
            'environment' => 'production',
        ];

        $response = $this->actingAs($this->operator)->postJson('/api/targets', $payload);

        $response->assertStatus(403);
    }
}
