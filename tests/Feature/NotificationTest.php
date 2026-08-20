<?php

namespace Tests\Feature;

use App\Enums\Notification\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->role = \App\Models\Role::create([
            'name' => 'admin',
            'slug' => 'admin',
            'permissions' => []
        ]);
    }

    protected function createUser()
    {
        return User::factory()->create(['role_id' => $this->role->id]);
    }

    public function test_can_fetch_notifications()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $service = app(NotificationService::class);
        $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => 'Test alert']);

        $response = $this->getJson('/api/notifications');
        $response->assertStatus(200)
                 ->assertJsonPath('data.0.type', 'system_alert')
                 ->assertJsonPath('data.0.data.message', 'Test alert');
    }

    public function test_can_fetch_unread_count()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $service = app(NotificationService::class);
        $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => '1']);
        $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => '2']);

        $response = $this->getJson('/api/notifications/unread-count');
        $response->assertStatus(200)
                 ->assertJsonPath('unread_count', 2);
    }

    public function test_can_mark_notification_as_read()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $service = app(NotificationService::class);
        $notification = $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => '1']);

        $response = $this->postJson("/api/notifications/{$notification->id}/mark-read");
        $response->assertStatus(200);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_can_mark_all_as_read()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $service = app(NotificationService::class);
        $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => '1']);
        $service->send($user, NotificationType::SYSTEM_ALERT, ['message' => '2']);

        $response = $this->postJson("/api/notifications/mark-all-read");
        $response->assertStatus(200);

        $this->assertEquals(0, $user->unreadNotifications()->count());
    }

    public function test_tenant_isolation()
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $service = app(NotificationService::class);
        $notification = $service->send($user1, NotificationType::SYSTEM_ALERT, ['message' => 'User 1 alert']);

        $this->actingAs($user2);
        
        $response = $this->getJson("/api/notifications/{$notification->id}");
        $response->assertStatus(404);
    }
}
