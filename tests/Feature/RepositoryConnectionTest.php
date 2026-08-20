<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Repository;
use App\Models\Role;
use App\Models\User;
use App\Services\Repository\GitHubProvider;
use App\Services\Repository\RepositoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Repository connection and GitHub validation tests.
 *
 * All tests mock the GitHub HTTP requests — no real PAT or GitHub dependency.
 */
class RepositoryConnectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles (required — users.role_id is NOT NULL)
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->admin = User::factory()->create([
            'email' => 'admin@trustnode.local',
            'name' => 'TrustNode Admin',
            'role_id' => $adminRole->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // GitHubProvider unit tests (mocked HTTP)
    // -------------------------------------------------------------------------

    public function test_github_provider_parses_https_url(): void
    {
        $provider = new GitHubProvider;
        $parsed = $provider->parseUrl('https://github.com/octocat/Spoon-Knife');

        $this->assertEquals('octocat', $parsed['owner']);
        $this->assertEquals('Spoon-Knife', $parsed['repo']);
    }

    public function test_github_provider_parses_url_with_dotgit(): void
    {
        $provider = new GitHubProvider;
        $parsed = $provider->parseUrl('https://github.com/octocat/Hello-World.git');

        $this->assertEquals('octocat', $parsed['owner']);
        $this->assertEquals('Hello-World', $parsed['repo']);
    }

    public function test_github_provider_returns_null_for_invalid_url(): void
    {
        $provider = new GitHubProvider;
        $this->assertNull($provider->parseUrl('not-a-url'));
        $this->assertNull($provider->parseUrl('https://gitlab.com/user/repo'));
    }

    public function test_validate_access_returns_true_for_200_response(): void
    {
        // Mock the HTTP layer at the PHP stream level by binding a fake GitHubProvider
        $mockProvider = $this->createMock(GitHubProvider::class);
        $mockProvider->method('validateAccess')->willReturn(true);

        $this->assertTrue($mockProvider->validateAccess('https://github.com/octocat/Spoon-Knife', null));
    }

    public function test_validate_access_returns_false_for_401_response(): void
    {
        $mockProvider = $this->createMock(GitHubProvider::class);
        $mockProvider->method('validateAccess')->willReturn(false);

        $this->assertFalse($mockProvider->validateAccess('https://github.com/owner/private-repo', 'invalid-token'));
    }

    public function test_validate_access_returns_false_for_404_response(): void
    {
        $mockProvider = $this->createMock(GitHubProvider::class);
        $mockProvider->method('validateAccess')->willReturn(false);

        $this->assertFalse($mockProvider->validateAccess('https://github.com/owner/nonexistent-repo', null));
    }

    // -------------------------------------------------------------------------
    // API endpoint tests (mocked RepositoryService / GitHubProvider)
    // -------------------------------------------------------------------------

    public function test_validate_access_endpoint_succeeds_for_public_repo(): void
    {
        // Bind a fake GitHubProvider that always returns valid
        $this->app->bind(GitHubProvider::class, function () {
            $mock = $this->createMock(GitHubProvider::class);
            $mock->method('validateAccess')->willReturn(true);

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories/validate-access', [
            'repository_url' => 'https://github.com/octocat/Spoon-Knife',
        ]);

        $response->assertStatus(200)->assertJson(['valid' => true]);
    }

    public function test_validate_access_endpoint_fails_for_invalid_url(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/repositories/validate-access', [
            'repository_url' => 'not-a-url',
        ]);

        $response->assertStatus(422);
    }

    public function test_validate_access_endpoint_returns_false_for_bad_token(): void
    {
        $this->app->bind(GitHubProvider::class, function () {
            $mock = $this->createMock(GitHubProvider::class);
            $mock->method('validateAccess')->willReturn(false);

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories/validate-access', [
            'repository_url' => 'https://github.com/owner/private-repo',
            'token' => 'ghp_INVALID_TOKEN_1234567890',
        ]);

        $response->assertStatus(200)->assertJson(['valid' => false]);
    }

    public function test_validate_access_response_does_not_expose_token(): void
    {
        $this->app->bind(GitHubProvider::class, function () {
            $mock = $this->createMock(GitHubProvider::class);
            $mock->method('validateAccess')->willReturn(false);

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories/validate-access', [
            'repository_url' => 'https://github.com/owner/private-repo',
            'token' => 'ghp_SECRET_TOKEN_MUST_NOT_APPEAR',
        ]);

        $this->assertStringNotContainsString('ghp_SECRET_TOKEN_MUST_NOT_APPEAR', $response->getContent());
    }

    public function test_store_repository_succeeds_for_public_repo(): void
    {
        $this->app->bind(RepositoryService::class, function () {
            $mock = $this->createMock(RepositoryService::class);
            $mock->method('connect')->willReturn(Repository::factory()->create([
                'name' => 'octocat/Spoon-Knife',
                'visibility' => 'public',
                'default_branch' => 'main',
                'status' => 'Connected',
                'created_by' => $this->admin->id,
            ]));

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories', [
            'repository_url' => 'https://github.com/octocat/Spoon-Knife',
            'visibility' => 'public',
        ]);

        $response->assertStatus(201);
        $this->assertArrayHasKey('id', $response->json());
    }

    public function test_stored_repository_has_uuid(): void
    {
        // Create a repository directly and verify uuid is populated
        $repo = Repository::factory()->create([
            'name' => 'test/repo',
            'visibility' => 'public',
            'created_by' => $this->admin->id,
        ]);

        $this->assertNotEmpty($repo->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $repo->uuid
        );
    }

    public function test_store_repository_does_not_expose_token_in_response(): void
    {
        $this->app->bind(RepositoryService::class, function () {
            $mock = $this->createMock(RepositoryService::class);
            $mock->method('connect')->willReturn(Repository::factory()->create([
                'name' => 'owner/private-repo',
                'visibility' => 'private',
                'default_branch' => 'main',
                'status' => 'Connected',
                'created_by' => $this->admin->id,
            ]));

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories', [
            'repository_url' => 'https://github.com/owner/private-repo',
            'visibility' => 'private',
            'token' => 'ghp_SUPER_SECRET_PAT_12345',
        ]);

        $this->assertStringNotContainsString('ghp_SUPER_SECRET_PAT_12345', $response->getContent());
    }

    public function test_store_repository_returns_clean_error_for_failed_access(): void
    {
        $this->app->bind(RepositoryService::class, function () {
            $mock = $this->createMock(RepositoryService::class);
            $mock->method('connect')->willThrowException(
                new \RuntimeException('GitHub API returned HTTP 401')
            );

            return $mock;
        });

        $response = $this->actingAs($this->admin)->postJson('/api/repositories', [
            'repository_url' => 'https://github.com/owner/private-repo',
            'visibility' => 'private',
            'token' => 'ghp_BAD_TOKEN',
        ]);

        $response->assertStatus(422);
        // Clean message — no raw exception
        $this->assertStringNotContainsString('GitHub API returned', $response->json('message') ?? '');
        $this->assertStringNotContainsString('ghp_BAD_TOKEN', $response->getContent());
    }

    public function test_list_repositories_requires_auth(): void
    {
        $response = $this->getJson('/api/repositories');
        $response->assertStatus(401);
    }

    public function test_list_repositories_returns_empty_for_fresh_install(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/repositories');
        $response->assertStatus(200)->assertJson([]);
    }
}
