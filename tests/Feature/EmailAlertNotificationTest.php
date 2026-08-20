<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Models\User;
use App\Notifications\ReportFailedNotification;
use App\Notifications\ReportReadyNotification;
use App\Notifications\ScanCompletedNotification;
use App\Notifications\ScanFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Scan $scan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $userRole = Role::where('slug', 'operator')->first();
        $this->user = User::factory()->create(['role_id' => $userRole->id]);

        $this->scan = Scan::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Test Scan',
            'type' => 'repository',
            'target' => 'repo',
            'status' => 'queued',
            'engine' => 'repositoryscanner',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_scan_completion_sends_notification()
    {
        Notification::fake();
        
        $this->user->notify(new ScanCompletedNotification($this->scan, 5));

        Notification::assertSentTo(
            [$this->user], ScanCompletedNotification::class
        );
    }

    public function test_scan_failure_sends_notification()
    {
        Notification::fake();
        
        $this->user->notify(new ScanFailedNotification($this->scan, 'Error'));

        Notification::assertSentTo(
            [$this->user], ScanFailedNotification::class
        );
    }

    public function test_report_ready_sends_notification()
    {
        Notification::fake();
        
        $this->user->notify(new ReportReadyNotification($this->scan->id, $this->scan->name));

        Notification::assertSentTo(
            [$this->user], ReportReadyNotification::class
        );
    }

    public function test_report_failure_sends_notification()
    {
        Notification::fake();
        
        $this->user->notify(new ReportFailedNotification($this->scan->id, $this->scan->name, 'Error'));

        Notification::assertSentTo(
            [$this->user], ReportFailedNotification::class
        );
    }

    public function test_email_preference_respected()
    {
        $this->user->preferences = ['email' => false, 'in_app' => true];
        
        $notification = new ScanCompletedNotification($this->scan, 5);
        $channels = $notification->via($this->user);
        
        $this->assertNotContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    public function test_in_app_preference_respected()
    {
        $this->user->preferences = ['email' => true, 'in_app' => false];
        
        $notification = new ScanCompletedNotification($this->scan, 5);
        $channels = $notification->via($this->user);
        
        $this->assertContains('mail', $channels);
        $this->assertNotContains('database', $channels);
    }
}
