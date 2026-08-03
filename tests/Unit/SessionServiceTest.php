<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Authentication\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class SessionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SessionService $sessionService;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();

        $this->sessionService = $this->app->make(SessionService::class);
    }

    public function test_start_session_regenerates_session_id(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $oldSessionId = Session::getId();

        $sessionId = $this->sessionService->startSession($user, '127.0.0.1', 'Mozilla/5.0');

        $this->assertNotEmpty($sessionId);
        $this->assertNotEquals($oldSessionId, $sessionId);
        $this->assertEquals(Session::getId(), $sessionId);
    }

    public function test_terminate_session_clears_data_and_csrf(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
            'role_id' => $this->adminRole->id,
            'status' => UserStatus::ACTIVE,
        ]);

        $this->actingAs($user);

        Session::put('some-key', 'some-value');
        $oldToken = Session::token();

        $this->sessionService->terminateSession();

        $this->assertGuest();
        $this->assertFalse(Session::has('some-key'));
        $this->assertNotEquals($oldToken, Session::token());
    }
}
