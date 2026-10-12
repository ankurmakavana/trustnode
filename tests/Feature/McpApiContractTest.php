<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scan;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The endpoints and parameters the MCP server (mcp/trustnode_mcp.py) relies on. The MCP sends per_page for
 * lists and scan_id for findings, and reads the report status endpoint. This test runs them against the
 * isolated test database. It does not touch live data.
 */
class McpApiContractTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create(['role_id' => Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail()->id]);
        $this->org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $this->org->id])->users()->attach($this->admin->id);
    }

    private function scan(string $name): Scan
    {
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'target' => 'approved-app',
            'status' => ScanStatus::COMPLETED,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_scan_list_honours_per_page(): void
    {
        $this->scan('one');
        $this->scan('two');
        $this->scan('three');

        $response = $this->actingAs($this->admin)->getJson('/api/scans?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(2, $response->json('meta.per_page'));
    }

    public function test_finding_list_is_scoped_by_scan_id_and_honours_per_page(): void
    {
        $scan = $this->scan('scoped');
        $other = $this->scan('other');
        Finding::factory()->create(['scan_id' => $scan->id, 'created_by' => $this->admin->id]);
        Finding::factory()->create(['scan_id' => $other->id, 'created_by' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson("/api/findings?scan_id={$scan->id}&per_page=5");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($scan->id, $response->json('data.0.scan.id'));
    }

    public function test_report_status_is_404_when_no_report_exists(): void
    {
        $scan = $this->scan('no-report');

        // The MCP maps this 404 to report_status "none".
        $this->actingAs($this->admin)->getJson("/api/scans/{$scan->id}/report/status")->assertNotFound();
    }

    public function test_mcp_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/scans')->assertUnauthorized();
    }
}
