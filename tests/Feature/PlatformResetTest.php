<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\CodebaseProject;
use App\Models\Scan;
use App\Models\Finding;
use App\Services\ResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformResetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator']);
        $operatorRole = Role::firstOrCreate(['slug' => 'operator'], ['name' => 'Operator']);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@test.local',
        ]);

        $this->operator = User::factory()->create([
            'role_id' => $operatorRole->id,
            'email' => 'operator@test.local',
        ]);
    }

    public function test_unauthenticated_request_cannot_invoke_reset(): void
    {
        $response = $this->postJson('/api/settings/reset', [
            'confirmation' => ResetService::CONFIRMATION_PHRASE,
        ]);

        $response->assertStatus(401);
    }

    public function test_non_administrator_cannot_invoke_reset(): void
    {
        $response = $this->actingAs($this->operator)->postJson('/api/settings/reset', [
            'confirmation' => ResetService::CONFIRMATION_PHRASE,
        ]);

        $response->assertStatus(403);
    }

    public function test_invalid_confirmation_phrase_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/reset', [
            'confirmation' => 'WRONG PHRASE',
        ]);

        $response->assertStatus(422);
    }

    public function test_missing_confirmation_is_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/reset', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['confirmation']);
    }

    public function test_reset_service_clears_application_data_and_enables_fresh_setup(): void
    {
        $org = \App\Models\Organization::create([
            'name' => 'Test Org',
            'slug' => 'test-org',
        ]);

        // Seed some sample data
        CodebaseProject::create([
            'name' => 'Test Project',
            'path' => '/tmp/test-project',
            'status' => 'active',
            'organization_id' => $org->id,
        ]);

        $this->assertDatabaseHas('codebase_projects', ['name' => 'Test Project']);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        $resetService = new ResetService();
        $result = $resetService->executeReset(
            adminUser: $this->admin,
            confirmationPhrase: ResetService::CONFIRMATION_PHRASE,
            requireBackup: false
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseCount('codebase_projects', 0);
        $this->assertDatabaseCount('users', 0);

        // Verify setup status endpoint indicates setup is required
        $statusResponse = $this->getJson('/api/setup/status');
        $statusResponse->assertStatus(200);
        $statusResponse->assertJson(['setup_required' => true]);
    }

    public function test_reset_creates_verified_backup_file_and_hash(): void
    {
        $resetService = new ResetService();
        $result = $resetService->executeReset(
            adminUser: $this->admin,
            confirmationPhrase: ResetService::CONFIRMATION_PHRASE,
            requireBackup: true
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['backup']);
        $this->assertFileExists($result['backup']['file']);
        $this->assertGreaterThan(0, $result['backup']['size']);
        $this->assertEquals(hash_file('sha256', $result['backup']['file']), $result['backup']['sha256']);

        // Clean up generated test backup
        @unlink($result['backup']['file']);
    }

    public function test_reset_rolls_back_cleanly_on_failure(): void
    {
        $org = \App\Models\Organization::create([
            'name' => 'Persistent Org',
            'slug' => 'persistent-org',
        ]);

        $project = CodebaseProject::create([
            'name' => 'Persistent Project',
            'path' => '/tmp/persistent',
            'status' => 'active',
            'organization_id' => $org->id,
        ]);

        // Create mock or failing reset service that throws inside the transaction
        $failingService = new class extends ResetService {
            protected function performDatabaseReset(): array
            {
                \Illuminate\Support\Facades\DB::transaction(function () {
                    \Illuminate\Support\Facades\DB::table('codebase_projects')->delete();
                    throw new \RuntimeException('Simulated failure during table purge');
                });
                return [];
            }
        };

        try {
            $failingService->executeReset(
                adminUser: $this->admin,
                confirmationPhrase: ResetService::CONFIRMATION_PHRASE,
                requireBackup: false
            );
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated failure during table purge', $e->getMessage());
        }

        // Verify that database transaction rolled back and records remain intact
        $this->assertDatabaseHas('codebase_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
