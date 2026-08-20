<?php

namespace Tests\Feature;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;
use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;
use App\Models\Asset;
use App\Models\Finding;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Target;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    private Asset $asset;

    private Target $target;

    private Scan $scan;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Roles and Permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();
        $operatorRole = Role::where('slug', 'operator')->first();

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
        ]);

        $this->operator = User::factory()->create([
            'role_id' => $operatorRole->id,
        ]);

        $this->asset = Asset::create([
            'name' => 'SSO Firewall Edge',
            'type' => AssetType::IPV4,
            'value' => '10.10.1.254',
            'criticality' => AssetCriticality::CRITICAL,
            'status' => AssetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->target = Target::create([
            'name' => 'Edge Gateway Firewall',
            'type' => TargetType::IP_ADDRESS,
            'value' => '10.10.1.254',
            'environment' => TargetEnvironment::PRODUCTION,
            'criticality' => TargetCriticality::CRITICAL,
            'status' => TargetStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $this->scan = Scan::create([
            'name' => 'Network Port Vulnerability Audit',
            'type' => ScanType::PORT_DISCOVERY,
            'engine' => ScanEngine::NMAP,
            'target' => '10.10.1.254',
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_can_list_and_filter_findings()
    {
        Finding::factory()->count(3)->create([
            'asset_id' => $this->asset->id,
            'target_id' => $this->target->id,
            'scan_id' => $this->scan->id,
            'assigned_analyst' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/findings');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_finding_via_api()
    {
        $payload = [
            'title' => 'SSO Token Leakage in Response Headers',
            'cve' => 'CVE-2024-99999',
            'cvss_score' => 8.8,
            'severity' => 'high',
            'status' => 'open',
            'category' => 'web_application',
            'cwe' => 'CWE-200',
            'description' => 'Auth middleware exposes access token inside response header values.',
            'technical_details' => 'Inspect Authorization response headers under load.',
            'business_impact' => 'Token replay attacks could compromise administrative accounts.',
            'remediation' => 'Filter header values prior to response rendering.',
            'evidence' => 'X-Debug-Auth-Token: ey...',
            'asset_id' => $this->asset->id,
            'target_id' => $this->target->id,
            'scan_id' => $this->scan->id,
            'assigned_analyst' => $this->admin->id,
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/findings', $payload);

        $response->assertStatus(211)
            ->assertJsonPath('data.title', 'SSO Token Leakage in Response Headers')
            ->assertJsonPath('data.severity', 'high');

        $this->assertDatabaseHas('findings', [
            'title' => 'SSO Token Leakage in Response Headers',
            'cve' => 'CVE-2024-99999',
            'cvss_score' => 8.8,
        ]);

        $this->assertDatabaseHas('finding_activity_logs', [
            'action' => 'created',
        ]);
    }

    public function test_can_update_finding_and_writes_activity_log()
    {
        $finding = Finding::factory()->create([
            'title' => 'SSO Token Leakage',
            'severity' => FindingSeverity::MEDIUM,
            'status' => FindingStatus::OPEN,
            'category' => 'web_application',
            'asset_id' => $this->asset->id,
            'created_by' => $this->admin->id,
        ]);

        $payload = [
            'title' => 'SSO Token Leakage Updated',
            'severity' => 'high',
            'status' => 'in_progress',
            'category' => 'web_application',
        ];

        $response = $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'SSO Token Leakage Updated')
            ->assertJsonPath('data.severity', 'high')
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('finding_activity_logs', [
            'finding_id' => $finding->id,
            'action' => 'updated',
        ]);
    }

    public function test_operator_is_denied_from_creating_findings()
    {
        $payload = [
            'title' => 'SSO Token Leakage',
            'severity' => 'high',
            'category' => 'web_application',
        ];

        $response = $this->actingAs($this->operator)->postJson('/api/findings', $payload);

        $response->assertStatus(403);
    }
    public function test_can_get_finding_detail()
    {
        $finding = Finding::factory()->create([
            'title' => 'SSO Token Leakage',
            'severity' => FindingSeverity::MEDIUM,
            'status' => FindingStatus::OPEN,
            'category' => 'web_application',
            'asset_id' => $this->asset->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'SSO Token Leakage');
    }

    public function test_getting_nonexistent_finding_returns_404()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/findings/999999');

        $response->assertStatus(404);
    }

    public function test_evidence_and_technical_details_mask_secrets()
    {
        $finding = Finding::factory()->create([
            'title' => 'Secret exposed',
            'category' => 'secret_leak',
            'evidence' => 'Here is the password: mysecretpassword123!',
            'technical_details' => 'Found token="supersecrettoken"',
            'asset_id' => $this->asset->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}");

        $response->assertStatus(200);
        $evidence = $response->json('data.evidence');
        $tech = $response->json('data.technical_details');

        $this->assertStringNotContainsString('mysecretpassword123', $evidence);
        $this->assertStringNotContainsString('supersecrettoken', $tech);
        $this->assertStringContainsString('REDACTED IN API RESPONSE', $evidence);
        $this->assertStringContainsString('REDACTED IN API RESPONSE', $tech);
    }
}
