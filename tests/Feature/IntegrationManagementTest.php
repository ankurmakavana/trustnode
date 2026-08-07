<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@trustnode.local',
        ]);
    }

    public function test_can_list_integration_stats(): void
    {
        $integration = Integration::create([
            'name' => 'Corporate Network Nmap',
            'code' => 'nmap',
            'type' => 'scanner',
            'status' => 'Connected',
            'health_status' => 'Healthy',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/integrations/stats');

        $response->assertStatus(200)
            ->assertJsonFragment(['code' => 'nmap', 'status' => 'Connected']);
    }

    public function test_can_configure_integration(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/integrations', [
            'name' => 'Enterprise Nessus Scanner',
            'code' => 'nessus',
            'type' => 'scanner',
            'host' => '192.168.1.100',
            'port' => 8834,
            'username' => 'nessus_admin',
            'password' => 'secret123',
            'tls' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('integrations', [
            'code' => 'nessus',
            'status' => 'Connected',
            'health_status' => 'Healthy',
        ]);
    }

    public function test_unauthenticated_api_request_returns_401(): void
    {
        $response = $this->get('/api/integrations/stats');
        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_configure_integration_fails_validation_for_missing_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/integrations', [
            'name' => '',
            'code' => 'invalid-code',
            'type' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'code', 'type']);
    }

    public function test_can_validate_connection(): void
    {
        $integration = Integration::create([
            'name' => 'OpenVAS Local scanner',
            'code' => 'greenbone',
            'type' => 'scanner',
            'status' => 'Connected',
            'health_status' => 'Healthy',
            'port' => 999,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/integrations/{$integration->id}/validate");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'Unreachable']);
    }

    public function test_can_trigger_import_data(): void
    {
        $integration = Integration::create([
            'name' => 'Burp REST interface',
            'code' => 'burp',
            'type' => 'scanner',
            'status' => 'Connected',
            'health_status' => 'Healthy',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/integrations/{$integration->id}/import", [
            'records_count' => 12,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('integration_jobs', [
            'integration_id' => $integration->id,
            'imported_records' => 12,
        ]);
    }
}
