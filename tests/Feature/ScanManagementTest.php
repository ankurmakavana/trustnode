<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\Target\TargetType;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScanManagementTest extends TestCase
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
     * Test listing scans with filters.
     */
    public function test_can_list_and_filter_scans()
    {
        Scan::create([
            'name' => 'Web App Vulnerability Check',
            'type' => ScanType::WEB_APPLICATION,
            'engine' => ScanEngine::OWASP_ZAP,
            'target' => 'https://auth.internal',
            'status' => ScanStatus::RUNNING,
            'progress' => 45,
            'created_by' => $this->admin->id,
        ]);

        Scan::create([
            'name' => 'Internal Port Audit',
            'type' => ScanType::PORT_DISCOVERY,
            'engine' => ScanEngine::NMAP,
            'target' => '10.10.1.1',
            'status' => ScanStatus::COMPLETED,
            'progress' => 100,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/scans?search=Port');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Internal Port Audit');
    }

    /**
     * Test scan creation validations.
     */
    public function test_can_create_scan_via_api()
    {
        $payload = [
            'name' => 'Internal Domain Scan',
            'type' => 'network_ip',
            'engine' => 'nmap',
            'target' => '10.10.2.1',
            'status' => 'queued',
            'progress' => 0,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/scans', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Internal Domain Scan');

        $this->assertDatabaseHas('scans', [
            'target' => '10.10.2.1',
        ]);
    }

    /**
     * Test updating scan log changes.
     */
    public function test_can_update_scan_and_writes_activity_log()
    {
        $scan = Scan::create([
            'name' => 'Weekly Endpoint Check',
            'type' => ScanType::API_VULNERABILITY,
            'engine' => ScanEngine::NUCLEI,
            'target' => 'api.internal',
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'name' => 'Weekly Endpoint Check Updated',
            'type' => 'api_vulnerability',
            'engine' => 'nuclei',
            'target' => 'api.internal',
            'status' => 'running',
            'progress' => 50,
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/scans/{$scan->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.progress', 50);

        $this->assertDatabaseHas('scan_activity_logs', [
            'scan_id' => $scan->id,
            'action' => 'updated',
        ]);
    }

    /**
     * Test operators cannot register scans.
     */
    public function test_operator_is_denied_from_creating_scans()
    {
        $payload = [
            'name' => 'Denied Scanner Exec',
            'type' => 'port_discovery',
            'engine' => 'nmap',
            'target' => '192.168.1.1',
        ];

        $response = $this->actingAs($this->operator)->postJson('/api/scans', $payload);

        $response->assertStatus(403);
    }

    /**
     * Test targetRelation belongsTo mapping logic.
     */
    public function test_scan_belongs_to_target_relationship()
    {
        $target = Target::create([
            'name' => 'Edge Gateway Firewall',
            'type' => TargetType::IP_ADDRESS,
            'value' => '10.10.1.254',
            'created_by' => $this->admin->id,
        ]);

        $scan = Scan::create([
            'name' => 'SSO Gate Vuln Assessment',
            'type' => ScanType::WEB_APPLICATION,
            'engine' => ScanEngine::OWASP_ZAP,
            'target' => '10.10.1.254',
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => $this->admin->id,
        ]);

        $this->assertNotNull($scan->targetRelation);
        $this->assertEquals($target->id, $scan->targetRelation->id);
        $this->assertEquals('Edge Gateway Firewall', $scan->targetRelation->name);
    }
}
