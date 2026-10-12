<?php

namespace Tests\Feature;

use App\Enums\CodebaseProject\CodebaseProjectStatus;
use App\Models\CodebaseProject;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Contract between the CLI and the API. The CLI (cli/src/Commands/Project*.php) reads these exact
 * fields and status codes. This test runs the real endpoints in the isolated test database, so a server
 * change that breaks the CLI fails here. Uses a temporary mount. No live data.
 */
class CliApiContractTest extends TestCase
{
    use RefreshDatabase;

    private string $mount;
    private User $admin;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->mount = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_cli_contract_' . uniqid();
        mkdir($this->mount . DIRECTORY_SEPARATOR . 'approved-app', 0777, true);
        config(['agent.scan_mount_dir' => $this->mount]);

        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->firstOrFail()->id]);
        $this->org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $this->org->id])->users()->attach($this->admin->id);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->mount)) {
            rmdir($this->mount . DIRECTORY_SEPARATOR . 'approved-app');
            rmdir($this->mount);
        }
        parent::tearDown();
    }

    public function test_project_list_returns_the_fields_the_cli_reads(): void
    {
        CodebaseProject::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'name' => 'Approved app',
            'path' => 'approved-app',
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/projects');

        $response->assertOk();
        $project = $response->json('data.0');
        foreach (['id', 'name', 'path', 'scan_profile', 'status'] as $field) {
            $this->assertArrayHasKey($field, $project, "CLI project:list reads '{$field}'.");
        }
        $this->assertSame('Approved app', $project['name']);
    }

    public function test_project_registration_returns_the_id_the_cli_prints(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/projects', [
            'name' => 'Approved app',
            'path' => 'approved-app',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.id'), 'CLI project:add prints data.id.');
    }

    public function test_project_scan_returns_the_message_the_cli_prints(): void
    {
        $project = CodebaseProject::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'path' => 'approved-app',
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);
        DB::table('agent_states')->insert([
            'agent_id' => 'contract-agent',
            'state' => json_encode(['state' => 'running']),
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/projects/{$project->id}/scan");

        $response->assertOk();
        $this->assertSame('Scan queued successfully.', $response->json('message'), 'CLI project:scan prints message.');
    }

    public function test_scan_of_an_unregistered_project_is_404_as_the_cli_expects(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/projects/999999/scan');

        $response->assertNotFound();
    }

    public function test_unauthenticated_calls_are_rejected_as_the_cli_expects(): void
    {
        $this->getJson('/api/projects')->assertUnauthorized();
    }
}
