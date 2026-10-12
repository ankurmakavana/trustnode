<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Organization;
use App\Models\Team;
use App\Models\Role;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Database\Seeders\RolePermissionSeeder;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles for testing since setup depends on ADMINISTRATOR role existing
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_setup_status_returns_true_when_no_users_exist()
    {
        $response = $this->getJson('/api/setup/status');

        $response->assertStatus(200)
                 ->assertJson(['setup_required' => true]);
    }

    public function test_setup_status_returns_false_when_users_exist()
    {
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->getJson('/api/setup/status');

        $response->assertStatus(200)
                 ->assertJson(['setup_required' => false]);
    }

    public function test_first_developer_can_create_account()
    {
        $this->assertDatabaseCount('users', 0);

        $response = $this->postJson('/api/setup', [
            'name' => 'First Developer',
            'email' => 'dev@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ]);

        $response->assertStatus(200);

        // Verify User was created
        $this->assertDatabaseCount('users', 1);
        $user = User::first();
        $this->assertEquals('First Developer', $user->name);
        $this->assertEquals('dev@trustnode.local', $user->email);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        $this->assertEquals($adminRole->id, $user->role_id);

        // Verify Organization was created
        $this->assertDatabaseCount('organizations', 1);
        $org = Organization::first();
        $this->assertEquals('default-organization', $org->slug);

        // Verify Team was created
        $this->assertDatabaseCount('teams', 1);
        $team = Team::first();
        $this->assertEquals('default-team', $team->slug);
        $this->assertEquals($org->id, $team->organization_id);

        // Verify membership
        $this->assertTrue($user->teams->contains($team));
    }

    public function test_setup_is_locked_after_initialization()
    {
        $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
        User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->postJson('/api/setup', [
            'name' => 'Second Developer',
            'email' => 'dev2@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Setup has already been completed.']);
                 
        $this->assertDatabaseCount('users', 1);
    }
    
    public function test_failed_setup_does_not_leave_partial_state()
    {
        // Force an error by causing a duplicate team slug after org creation (this is hard to simulate directly without mocking, 
        // but validation errors prevent entering the transaction).
        // Let's test validation failure.
        $response = $this->postJson('/api/setup', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'short'
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_repeated_setup_after_completion_does_not_create_a_second_admin()
    {
        $payload = [
            'name' => 'First Developer',
            'email' => 'dev@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ];

        $this->postJson('/api/setup', $payload)->assertStatus(200);
        $this->postJson('/api/setup', array_merge($payload, ['email' => 'dev2@trustnode.local']))
            ->assertStatus(403);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'dev2@trustnode.local']);
    }

    public function test_setup_is_refused_while_another_setup_holds_the_lock()
    {
        $this->swapSetupLock(new FakeSetupLock(throwOnBlock: true));

        $response = $this->postJson('/api/setup', [
            'name' => 'Concurrent Developer',
            'email' => 'dev@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ]);

        $response->assertStatus(409)
                 ->assertJson(['message' => 'Setup is already in progress.']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_setup_rechecks_owner_after_acquiring_the_lock()
    {
        // Simulates a second request that finishes setup while this one waits for the lock.
        $lock = new FakeSetupLock(beforeBlock: function () {
            $adminRole = Role::where('slug', UserRole::ADMINISTRATOR->value)->first();
            User::factory()->create(['role_id' => $adminRole->id]);
        });
        $this->swapSetupLock($lock);

        $response = $this->postJson('/api/setup', [
            'name' => 'Late Developer',
            'email' => 'late@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Setup has already been completed.']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'late@trustnode.local']);
        $this->assertTrue($lock->released, 'Setup lock must be released after a refused setup.');
    }

    public function test_setup_lock_is_released_after_successful_setup()
    {
        $lock = new FakeSetupLock();
        $this->swapSetupLock($lock);

        $this->postJson('/api/setup', [
            'name' => 'First Developer',
            'email' => 'dev@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ])->assertStatus(200);

        $this->assertTrue($lock->blocked);
        $this->assertTrue($lock->released);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_setup_failure_does_not_leak_exception_details()
    {
        // Renaming the administrator role makes setup fail after validation and the lock are passed.
        Role::where('slug', UserRole::ADMINISTRATOR->value)->update(['slug' => 'renamed-for-test']);

        $response = $this->postJson('/api/setup', [
            'name' => 'First Developer',
            'email' => 'dev@trustnode.local',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!'
        ]);

        $response->assertStatus(500)
                 ->assertJson(['message' => 'Failed to initialize setup.'])
                 ->assertJsonMissingPath('error');

        $this->assertStringNotContainsString('property', $response->getContent());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('organizations', 0);
    }

    private function swapSetupLock(FakeSetupLock $lock): void
    {
        Cache::shouldReceive('lock')
            ->once()
            ->with('trustnode:setup-lock', 30)
            ->andReturn($lock);
    }
}

class FakeSetupLock
{
    public bool $blocked = false;
    public bool $released = false;

    public function __construct(
        private bool $throwOnBlock = false,
        private ?\Closure $beforeBlock = null,
    ) {
    }

    public function block($seconds, $callback = null)
    {
        $this->blocked = true;

        if ($this->beforeBlock) {
            ($this->beforeBlock)();
        }

        if ($this->throwOnBlock) {
            throw new LockTimeoutException();
        }

        return true;
    }

    public function release()
    {
        $this->released = true;

        return true;
    }
}
