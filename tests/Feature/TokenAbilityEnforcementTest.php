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
 * Personal access tokens carry abilities. A token must not perform an operation its abilities do not
 * allow, and a token with no abilities must not read data. Every API request made with a token is checked
 * here. Positive controls show that allowed operations still work.
 */
class TokenAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;
    private CodebaseProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->firstOrFail()->id]);
        $this->org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $this->org->id])->users()->attach($this->admin->id);

        $this->project = CodebaseProject::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'path' => 'approved-app',
            'status' => CodebaseProjectStatus::ACTIVE,
        ]);

        DB::table('agent_states')->insert([
            'agent_id' => 'token-agent',
            'state' => json_encode(['state' => 'running']),
        ]);
    }

    /** Plain-text token with the given abilities, as the API issues it. */
    private function tokenWith(array $abilities): string
    {
        return $this->admin->createToken('test', $abilities)->plainTextToken;
    }

    private function asToken(string $plain)
    {
        return $this->withHeader('Authorization', 'Bearer ' . $plain)->withHeader('Accept', 'application/json');
    }

    public function test_positive_control_a_read_token_can_list_scans(): void
    {
        $token = $this->tokenWith(['scans:read']);

        $this->asToken($token)->getJson('/api/scans')->assertOk();
    }

    public function test_read_only_token_cannot_start_a_scan(): void
    {
        $token = $this->tokenWith(['scans:read', 'findings:read']);

        $this->asToken($token)->postJson('/api/scans', [
            'name' => 'token scan',
            'type' => 'repository',
            'engine' => 'repositoryscanner',
            'target' => 'approved-app',
        ])->assertForbidden();
    }

    public function test_read_only_token_cannot_queue_a_project_scan(): void
    {
        $token = $this->tokenWith(['scans:read', 'findings:read']);

        $this->asToken($token)->postJson("/api/projects/{$this->project->id}/scan")->assertForbidden();
        $this->assertSame(0, DB::table('agent_tasks')->count(), 'No agent task may be queued by a read-only token.');
    }

    public function test_read_only_token_cannot_create_a_finding(): void
    {
        $token = $this->tokenWith(['findings:read']);

        $this->asToken($token)->postJson('/api/findings', [
            'title' => 'injected by token',
            'severity' => 'low',
            'category' => 'test',
        ])->assertForbidden();
    }

    public function test_read_only_token_cannot_delete_a_scan(): void
    {
        $token = $this->tokenWith(['scans:read']);

        $this->asToken($token)->deleteJson('/api/scans/1')->assertForbidden();
    }

    public function test_token_without_abilities_cannot_read_scans(): void
    {
        $token = $this->tokenWith([]);

        $this->asToken($token)->getJson('/api/scans')->assertForbidden();
    }

    public function test_token_without_the_projects_ability_cannot_register_a_project(): void
    {
        $token = $this->tokenWith(['scans:read']);

        $this->asToken($token)->postJson('/api/projects', [
            'name' => 'injected',
            'path' => 'approved-app',
            'scan_profile' => 'standard',
            'continuous_scan_enabled' => false,
        ])->assertForbidden();
    }

    public function test_positive_control_a_write_token_can_start_a_scan(): void
    {
        $token = $this->tokenWith(['scans:write']);

        $this->asToken($token)->postJson('/api/scans', [
            'name' => 'token scan',
            'type' => 'repository',
            'engine' => 'repositoryscanner',
            'target' => 'approved-app',
        ])->assertCreated();
    }

    public function test_a_token_cannot_mint_another_token(): void
    {
        // Otherwise a restricted token could create one with every ability.
        $token = $this->tokenWith(['scans:read']);

        $this->asToken($token)->postJson('/api/auth/tokens', [
            'name' => 'escalated',
            'abilities' => ['scans:write', 'findings:write'],
        ])->assertForbidden();
        $this->assertSame(1, DB::table('personal_access_tokens')->count(), 'No new token may be created.');
    }

    public function test_a_token_can_read_its_own_identity(): void
    {
        $token = $this->tokenWith([]);

        $this->asToken($token)->getJson('/api/auth/me')->assertOk();
    }

    public function test_a_token_can_revoke_itself(): void
    {
        $plain = $this->tokenWith(['scans:read']);
        $id = (int) explode('|', $plain)[0];

        $this->asToken($plain)->deleteJson("/api/auth/tokens/{$id}")->assertOk();
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_restricted_token_cannot_list_projects_without_the_projects_ability(): void
    {
        $token = $this->tokenWith(['scans:read', 'findings:read', 'reports:read']);

        $this->asToken($token)->getJson('/api/projects')->assertForbidden();
    }

    public function test_session_users_are_not_restricted_by_token_abilities(): void
    {
        // Positive control: the browser session is not a token, so its operations are unaffected.
        $this->actingAs($this->admin)->getJson('/api/scans')->assertOk();
    }
}
