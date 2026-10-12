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
use App\Services\Remediation\AiFixPromptBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Server-side AI fix prompt: content, redaction of credentials that were stored before redaction existed,
 * advisory wording, and tenant isolation. Values are synthetic.
 */
class AiFixPromptTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_AWS = 'AKIAFAKEFAKEFAKEFAKE';
    private const FAKE_PASSWORD = 'FAKEdbpass123';

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

    private function findingInOrg(array $attributes = []): Finding
    {
        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'prompt scan',
            'target' => 'approved-app',
            'status' => ScanStatus::COMPLETED,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);

        return Finding::factory()->create(array_merge([
            'scan_id' => $scan->id,
            'title' => 'Credential used in query',
            'category' => 'Injection',
            'cwe' => 'CWE-89',
            'severity' => 'high',
            'description' => 'Query built from request input.',
            'remediation' => 'Use parameterised queries.',
            'technical_details' => 'app/Db.php line 12',
            'evidence' => 'db->query("SELECT 1")',
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    public function test_prompt_carries_the_finding_identity_and_the_advisory_rules(): void
    {
        $finding = $this->findingInOrg();

        $response = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}/ai-prompt");

        $response->assertOk();
        $prompt = $response->json('data.prompt');
        $this->assertSame($finding->finding_id, $response->json('data.finding_id'));
        $this->assertStringContainsString($finding->finding_id, $prompt);
        $this->assertStringContainsString('Severity: high', $prompt);
        $this->assertStringContainsString('Injection, CWE-89', $prompt);
        $this->assertStringContainsString('Do not apply it, commit it or push it.', $prompt);
        $this->assertStringContainsString('Do not print, copy or commit any credential', $prompt);
    }

    public function test_credentials_stored_before_redaction_are_not_in_the_prompt(): void
    {
        $finding = $this->findingInOrg();
        // A row written by the old code, bypassing the model hook.
        DB::table('findings')->where('id', $finding->id)->update([
            'evidence' => 'aws=' . self::FAKE_AWS . ' DB_PASSWORD=' . self::FAKE_PASSWORD,
            'technical_details' => 'token=' . self::FAKE_AWS,
        ]);

        $prompt = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}/ai-prompt")->json('data.prompt');

        $this->assertStringNotContainsString(self::FAKE_AWS, $prompt);
        $this->assertStringNotContainsString(self::FAKE_PASSWORD, $prompt);
        $this->assertStringContainsString(\App\Services\SecretRedactor::PLACEHOLDER, $prompt);
    }

    public function test_long_evidence_is_truncated_in_the_prompt(): void
    {
        $finding = $this->findingInOrg(['evidence' => str_repeat('x', 2000)]);

        $prompt = app(AiFixPromptBuilder::class)->build($finding);

        $this->assertStringContainsString('...[EVIDENCE TRUNCATED]', $prompt);
        $this->assertStringNotContainsString(str_repeat('x', 1000), $prompt);
    }

    public function test_a_finding_in_another_organization_is_not_found(): void
    {
        $finding = $this->findingInOrg();
        $outsider = User::factory()->create(['role_id' => Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail()->id]);
        Team::factory()->create(['organization_id' => Organization::factory()->create()->id])->users()->attach($outsider->id);

        $this->actingAs($outsider)->getJson("/api/findings/{$finding->id}/ai-prompt")->assertNotFound();
    }

    public function test_the_prompt_endpoint_requires_authentication(): void
    {
        $finding = $this->findingInOrg();

        $this->getJson("/api/findings/{$finding->id}/ai-prompt")->assertUnauthorized();
    }
}
