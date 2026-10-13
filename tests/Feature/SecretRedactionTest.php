<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Handlers\AgentReportFindingTaskHandler;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Scan\Scanners\AbstractRegexScanner;
use App\Services\Scan\Scanners\SecretScanner;
use App\Services\SecretRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Secrets must be redacted server-side before they are stored or returned, for every finding
 * category, not only secret-category findings. Values below are synthetic.
 */
class SecretRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_PASSWORD = 'FAKEdbpass123';
    private const FAKE_AWS = 'AKIAFAKEFAKEFAKEFAKE';

    private User $admin;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        \App\Services\TenantContext::clear();

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail();
        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->org = Organization::factory()->create();
        $team = Team::factory()->create(['organization_id' => $this->org->id]);
        $team->users()->attach($this->admin->id);
    }

    private function nonSecretCategoryScanner(): AbstractRegexScanner
    {
        return new class extends AbstractRegexScanner {
            protected function getRules(): array
            {
                return [[
                    'id' => 'TEST-INJECTION',
                    'title' => 'Query built from input',
                    'severity' => 'high',
                    'category' => 'Injection',
                    'cwe' => 'CWE-89',
                    'description' => 'Query construction from input.',
                    'remediation' => 'Use parameterized queries.',
                    'regex' => '/db->query\(.*\)/',
                ]];
            }
        };
    }

    public function test_secret_in_non_secret_category_evidence_is_redacted_before_it_is_returned_by_the_scanner()
    {
        $content = 'db->query("SELECT 1"); // password=' . self::FAKE_PASSWORD;

        $findings = $this->nonSecretCategoryScanner()->scan($content, explode("\n", $content), 'app/Db.php', '');

        $this->assertCount(1, $findings);
        $this->assertStringNotContainsString(self::FAKE_PASSWORD, $findings[0]->evidence);
        $this->assertStringContainsString(SecretRedactor::PLACEHOLDER, $findings[0]->evidence);
    }

    public function test_secret_category_evidence_is_fully_redacted_without_partial_reveal()
    {
        $content = 'AWS_ACCESS_KEY_ID=' . self::FAKE_AWS;

        $findings = app(SecretScanner::class)->scan($content, explode("\n", $content), '.env', '');

        $this->assertNotEmpty($findings);
        foreach ($findings as $finding) {
            $this->assertStringNotContainsString(self::FAKE_AWS, $finding->evidence);
            // The old masking kept the first and last four characters, so the suffix must not appear.
            $this->assertStringNotContainsString('FAKE', $finding->evidence);
            $this->assertStringNotContainsString('AKIA', $finding->evidence);
        }
    }

    public function test_agent_reported_finding_is_stored_without_raw_secrets_in_any_text_field()
    {
        $handler = app(AgentReportFindingTaskHandler::class);

        $handler->handle([
            'id' => 'task-redact-1',
            'type' => 'agent.report_finding',
            'agent_id' => 'agent-redact',
            'payload' => [
                'title' => 'Credential in configuration',
                'severity' => 'high',
                'description' => 'Configuration contains token=' . self::FAKE_AWS . ' in plain text.',
                'technical_details' => 'Found DB_PASSWORD=' . self::FAKE_PASSWORD . ' on line 3.',
                'remediation' => 'Rotate the value and load it from a secret store.',
                'evidence' => 'DB_PASSWORD=' . self::FAKE_PASSWORD . ' and key ' . self::FAKE_AWS,
                'created_by' => $this->admin->id,
            ],
        ]);

        $finding = Finding::withoutGlobalScopes()->where('title', 'Credential in configuration')->firstOrFail();

        foreach (['description', 'technical_details', 'evidence', 'remediation', 'title'] as $field) {
            $this->assertStringNotContainsString(self::FAKE_PASSWORD, (string) $finding->{$field}, "{$field} leaked");
            $this->assertStringNotContainsString(self::FAKE_AWS, (string) $finding->{$field}, "{$field} leaked");
        }
        $this->assertStringContainsString('DB_PASSWORD=', (string) $finding->evidence);
    }

    public function test_every_finding_write_path_stores_redacted_text()
    {
        $raw = 'DB_PASSWORD=' . self::FAKE_PASSWORD . ' and ' . self::FAKE_AWS;

        // Eloquent create (imports, API and tests).
        $created = Finding::factory()->create(['evidence' => $raw, 'description' => $raw]);

        // updateOrCreate (the scan jobs' path).
        $upserted = Finding::updateOrCreate(
            ['uuid' => (string) \Illuminate\Support\Str::uuid()],
            ['title' => 'Upserted', 'severity' => 'low', 'status' => 'open', 'category' => 'Injection', 'evidence' => $raw, 'created_by' => $this->admin->id]
        );

        foreach ([$created->id, $upserted->id] as $id) {
            $row = DB::table('findings')->where('id', $id)->first();
            $this->assertStringNotContainsString(self::FAKE_PASSWORD, (string) $row->evidence);
            $this->assertStringNotContainsString(self::FAKE_AWS, (string) $row->evidence);
            $this->assertStringNotContainsString(self::FAKE_PASSWORD, (string) $row->description);
        }
    }

    public function test_legacy_stored_raw_text_is_redacted_when_the_row_is_next_saved()
    {
        $finding = Finding::factory()->create(['evidence' => 'placeholder']);

        // Simulate a row written before server-side redaction existed.
        DB::table('findings')->where('id', $finding->id)->update(['evidence' => 'password=' . self::FAKE_PASSWORD]);

        $finding = Finding::withoutGlobalScopes()->findOrFail($finding->id);
        $finding->update(['remediation' => 'Rotate the credential.']);

        $this->assertStringNotContainsString(self::FAKE_PASSWORD, (string) DB::table('findings')->where('id', $finding->id)->value('evidence'));
    }

    public function test_report_input_is_redacted_in_memory_and_the_stored_row_is_not_changed()
    {
        $finding = Finding::factory()->create(['evidence' => 'placeholder']);
        DB::table('findings')->where('id', $finding->id)->update(['evidence' => 'password=' . self::FAKE_PASSWORD]);

        $loaded = Finding::withoutGlobalScopes()->findOrFail($finding->id);
        $loaded->redactSecrets();

        $this->assertStringNotContainsString(self::FAKE_PASSWORD, (string) $loaded->evidence);
        // In-memory only: the stored value still holds the legacy text, because nothing was saved.
        $this->assertStringContainsString(self::FAKE_PASSWORD, (string) DB::table('findings')->where('id', $finding->id)->value('evidence'));
    }

    public function test_observed_env_credentials_are_not_enqueued_in_raw_form()
    {
        // Runs the real scanner and observe handler against a temporary base path, so the
        // project's own .env is never read or written.
        $originalBasePath = app()->basePath();
        $tempBase = storage_path('app/redaction_observe_' . uniqid());
        mkdir($tempBase, 0755, true);
        file_put_contents($tempBase . DIRECTORY_SEPARATOR . '.env', implode("\n", [
            'AWS_ACCESS_KEY_ID=' . self::FAKE_AWS,
            'DB_PASSWORD=' . self::FAKE_PASSWORD,
        ]) . "\n");

        app()->setBasePath($tempBase);

        try {
            $captured = [];
            $queue = \Mockery::mock(\App\Contracts\AgentQueueInterface::class);
            $queue->shouldReceive('enqueue')->andReturnUsing(function ($agentId, $type, $payload) use (&$captured) {
                $captured[] = json_encode($payload);
                return 'task-' . count($captured);
            });

            $agentService = \Mockery::mock(\App\Services\AgentService::class);
            $agentService->shouldReceive('getAgentId')->andReturn('agent-env');

            \Illuminate\Support\Facades\Cache::flush();

            $handler = new \App\Handlers\AgentObserveEnvTaskHandler(app(SecretScanner::class), $queue, $agentService);

            // The worker sets this from the task owner; the observe handler requires it.
            \App\Services\TenantContext::setUserId($this->admin->id);
            $handler->handle(['id' => 'task-env-1', 'payload' => []]);

            $this->assertNotEmpty($captured, 'Expected the observation to report at least one finding.');
            $all = implode("\n", $captured);
            $this->assertStringNotContainsString(self::FAKE_AWS, $all);
            $this->assertStringNotContainsString(self::FAKE_PASSWORD, $all);
        } finally {
            \App\Services\TenantContext::clear();
            app()->setBasePath($originalBasePath);
            \Illuminate\Support\Facades\File::deleteDirectory($tempBase);
            \Mockery::close();
        }
    }

    public function test_secret_category_evidence_from_an_agent_is_never_stored_as_text()
    {
        // Older agent builds sent the first and last four characters of a credential. The stored value must
        // not contain any part of it, whatever the sender sends.
        $partialMask = substr(self::FAKE_PASSWORD, 0, 4) . str_repeat('*', 26) . substr(self::FAKE_PASSWORD, -4);

        app(AgentReportFindingTaskHandler::class)->handle([
            'id' => 'task-secret-evidence-1',
            'agent_id' => 'agent-env',
            'payload' => [
                'agent_id' => 'agent-env',
                'created_by' => $this->admin->id,
                'finding' => [
                    'title' => 'Exposed Private Token or Secret',
                    'severity' => 'high',
                    'category' => 'Secret',
                    'scanner' => 'RepositoryScanner',
                    'scannerRuleId' => 'SEC-SECRET-GENERIC',
                    'evidence' => $partialMask,
                    'description' => 'An API token, private key, or password was found hardcoded in the source code.',
                    'technicalDetails' => 'Vulnerability found in .env on line 28.',
                    'remediation' => 'Move hardcoded credentials to configuration environment variables.',
                ],
            ],
        ]);

        $stored = (string) DB::table('findings')->where('title', 'Exposed Private Token or Secret')->value('evidence');

        $this->assertSame('[REDACTED: SEC-SECRET-GENERIC]', $stored);
        $this->assertStringNotContainsString(substr(self::FAKE_PASSWORD, 0, 4), $stored);
        $this->assertStringNotContainsString(substr(self::FAKE_PASSWORD, -4), $stored);
    }

    public function test_api_response_redacts_token_shapes_in_non_secret_category_findings()
    {
        $scan = \App\Models\Scan::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'redaction scan',
            'target' => 'app',
            'status' => 'completed',
            'type' => \App\Enums\Scan\ScanType::REPOSITORY->value,
            'engine' => 'repositoryscanner',
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);

        $finding = Finding::factory()->create([
            'title' => 'Hardcoded credential usage',
            'category' => 'Injection',
            'evidence' => 'aws key used here: ' . self::FAKE_AWS,
            'technical_details' => 'Line includes ' . self::FAKE_AWS,
            'scan_id' => $scan->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}");

        $response->assertStatus(200);
        $this->assertStringNotContainsString(self::FAKE_AWS, $response->getContent());
    }
}
