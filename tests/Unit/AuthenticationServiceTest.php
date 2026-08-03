<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Auth\ChangePasswordData;
use App\DTO\Auth\LoginData;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Events\Auth\PasswordChanged;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserLoggedOut;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\AuthenticationFailedException;
use App\Models\Role;
use App\Models\User;
use App\Services\Authentication\AuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthenticationService $authService;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->authService = $this->app->make(AuthenticationService::class);
    }

    public function test_successful_login(): void
    {
        Event::fake([UserLoggedIn::class]);
        $this->authService = $this->app->make(AuthenticationService::class);

        $password = 'secret-pass';
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make($password),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $loginData = new LoginData(
            email: 'john@example.com',
            password: $password,
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0'
        );

        $result = $this->authService->login($loginData);

        $this->assertEquals($user->id, $result->user->id);
        $this->assertNotEmpty($result->sessionId);

        // Assert user tracking updated
        $user->refresh();
        $this->assertEquals('127.0.0.1', $user->last_login_ip);
        $this->assertNotNull($user->last_login_at);

        Event::assertDispatched(UserLoggedIn::class, function ($event) use ($user) {
            return $event->user->id === $user->id;
        });
    }

    public function test_login_throws_exception_for_invalid_password(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('correct-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $loginData = new LoginData(
            email: 'john@example.com',
            password: 'wrong-password',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authService->login($loginData);
    }

    public function test_login_throws_exception_for_suspended_account(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::SUSPENDED,
        ]);

        $loginData = new LoginData(
            email: 'john@example.com',
            password: 'password',
            ipAddress: '127.0.0.1',
            userAgent: 'Mozilla/5.0'
        );

        $this->expectException(AccountSuspendedException::class);

        $this->authService->login($loginData);
    }

    public function test_logout_terminates_session(): void
    {
        Event::fake([UserLoggedOut::class]);
        $this->authService = $this->app->make(AuthenticationService::class);

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user);

        $this->authService->logout($user);

        $this->assertGuest();
        Event::assertDispatched(UserLoggedOut::class);
    }

    public function test_change_password_success(): void
    {
        Event::fake([PasswordChanged::class]);
        $this->authService = $this->app->make(AuthenticationService::class);

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('old-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
            'remember_token' => 'old-remember-token',
        ]);

        $changePasswordData = new ChangePasswordData(
            currentPassword: 'old-password',
            newPassword: 'new-password'
        );

        $this->authService->changePassword($user, $changePasswordData);

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertNotEquals('old-remember-token', $user->remember_token);

        Event::assertDispatched(PasswordChanged::class);
    }

    public function test_change_password_fails_for_invalid_current_password(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('old-password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $changePasswordData = new ChangePasswordData(
            currentPassword: 'wrong-old-password',
            newPassword: 'new-password'
        );

        $this->expectException(AuthenticationFailedException::class);

        $this->authService->changePassword($user, $changePasswordData);
    }
}
