<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $userRole = Role::where('slug', 'operator')->first();
        
        $this->user1 = User::factory()->create(['role_id' => $userRole->id]);
        $this->user2 = User::factory()->create(['role_id' => $userRole->id]);
    }

    public function test_default_values_work()
    {
        $response = $this->actingAs($this->user1)->getJson('/api/users/preferences');
        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'notifications' => [
                    'in_app' => true,
                    'email' => true,
                ]
            ]
        ]);
    }

    public function test_user_can_update_own_preferences()
    {
        $response = $this->actingAs($this->user1)->putJson('/api/users/preferences', [
            'notifications' => [
                'in_app' => false,
                'email' => false,
            ]
        ]);

        $response->assertStatus(200);
        
        $this->user1->refresh();
        $this->assertFalse($this->user1->getPreference('in_app'));
        $this->assertFalse($this->user1->getPreference('email'));
    }

    public function test_preferences_persist_across_requests()
    {
        $this->actingAs($this->user1)->putJson('/api/users/preferences', [
            'notifications' => [
                'in_app' => false,
                'email' => true,
            ]
        ]);

        $response = $this->actingAs($this->user1)->getJson('/api/users/preferences');
        $response->assertJsonFragment([
            'in_app' => false,
            'email' => true,
        ]);
    }
}
