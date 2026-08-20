<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TestEmailNotification;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $userRole = Role::where('slug', 'operator')->first();
        $this->user = User::factory()->create(['role_id' => $userRole->id]);
    }

    public function test_admin_can_read_settings_but_password_is_hidden()
    {
        Setting::setValue('mail_host', 'smtp.example.com');
        Setting::setValue('mail_password', 'secret', true);

        $response = $this->actingAs($this->admin)->getJson('/api/settings/mail');

        $response->assertStatus(200);
        $response->assertJsonFragment(['mail_host' => 'smtp.example.com']);
        $response->assertJsonMissing(['mail_password' => 'secret']);
    }

    public function test_non_admin_cannot_read_settings()
    {
        $response = $this->actingAs($this->user)->getJson('/api/settings/mail');
        $response->assertStatus(403);
    }

    public function test_admin_can_update_settings()
    {
        $payload = [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.new.com',
            'mail_port' => 587,
            'mail_username' => 'testuser',
            'mail_password' => 'newsecret',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'alert@new.com',
            'mail_from_name' => 'Alerts',
        ];

        $response = $this->actingAs($this->admin)->putJson('/api/settings/mail', $payload);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('settings', [
            'key' => 'mail_host',
            'is_encrypted' => false,
        ]);
        
        $this->assertEquals('smtp.new.com', Setting::getValue('mail_host'));
        $this->assertEquals('newsecret', Setting::getValue('mail_password'));
        
        // Ensure password is encrypted in db
        $dbRow = \DB::table('settings')->where('key', 'mail_password')->first();
        $this->assertNotEquals('newsecret', $dbRow->value);
        $this->assertEquals(1, $dbRow->is_encrypted);
    }

    public function test_non_admin_cannot_update_settings()
    {
        $response = $this->actingAs($this->user)->putJson('/api/settings/mail', []);
        $response->assertStatus(403);
    }

    public function test_invalid_configuration_fails_validation()
    {
        $response = $this->actingAs($this->admin)->putJson('/api/settings/mail', [
            'mail_host' => 'smtp.new.com', // missing required fields
        ]);
        $response->assertStatus(422);
    }

    public function test_test_email_can_be_dispatched()
    {
        Notification::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/settings/test-email', [
            'to_email' => 'admin@test.com',
        ]);

        $response->assertStatus(200);
        Notification::assertSentOnDemand(TestEmailNotification::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'admin@test.com';
        });
    }
}
