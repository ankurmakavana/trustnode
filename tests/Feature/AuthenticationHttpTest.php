<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Flush Redis cache to eliminate throttle counter persistence between test runs
        Cache::flush();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
    }

    protected function tearDown(): void
    {
        // Clear rate limiter state between tests to prevent throttle bleed
        RateLimiter::clear('login');
        RateLimiter::clear('api');

        parent::tearDown();
    }

    public function test_api_successful_login(): void
    {
        $this->withoutMiddleware('throttle');

        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('secret-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'status',
                    'role' => ['name', 'slug'],
                    'last_login_at',
                    'last_login_ip',
                    'created_at',
                ],
                'meta' => [
                    'session_id',
                    'message',
                ],
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_api_login_invalid_credentials(): void
    {
        $this->withoutMiddleware('throttle');

        User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('secret-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid email or password.',
            ]);

        $this->assertGuest();
    }

    public function test_api_login_suspended_account(): void
    {
        $this->withoutMiddleware('throttle');

        User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('secret-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::SUSPENDED,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account has been suspended.',
            ]);

        $this->assertGuest();
    }

    public function test_api_logout(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('secret-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully.',
            ]);

        $this->assertGuest('web');
    }

    public function test_api_change_password(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => Hash::make('old-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $response = $this->actingAs($user)->postJson('/api/password/change', [
            'current_password' => 'old-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password changed successfully.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_api_invitation_acceptance(): void
    {
        $this->withoutMiddleware('throttle');

        $token = 'invitation-secret-token-123';
        $tokenHash = hash('sha256', $token);

        $invitation = UserInvitation::create([
            'email' => 'invitee@example.com',
            'token_hash' => $tokenHash,
            'role_id' => $this->adminRole->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // Verify GET invitation info works
        $getResponse = $this->getJson("/api/invitation/{$token}");
        $getResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'email',
                    'role',
                    'expires_at',
                    'accepted_at',
                    'revoked_at',
                ],
            ]);

        // Verify POST accept invitation works
        $postResponse = $this->postJson("/api/invitation/{$token}", [
            'name' => 'Invited User',
            'password' => 'new-secure-pass-123',
            'password_confirmation' => 'new-secure-pass-123',
        ]);

        $postResponse->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'name',
                    'email',
                    'status',
                    'role',
                ],
                'meta' => [
                    'message',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'invitee@example.com',
            'name' => 'Invited User',
        ]);

        $invitation->refresh();
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_api_validation_failures(): void
    {
        $this->withoutMiddleware('throttle');

        $response = $this->postJson('/api/login', [
            'email' => 'invalid-email-format',
            // Missing password
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_api_login_throttling(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'throttled@example.com',
                'password' => 'wrong',
            ]);
        }

        // 6th attempt should be throttled
        $response = $this->postJson('/api/login', [
            'email' => 'throttled@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }
}
