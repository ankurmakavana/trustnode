<?php

namespace Tests\Feature;

use App\Enums\Notification\NotificationType;
use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Repository;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use App\Services\Notification\NotificationContext;
use App\Services\Notification\NotificationRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }
    
    private function createUser($roleSlug = null, $status = UserStatus::ACTIVE)
    {
        if (!$roleSlug) {
            $roleSlug = UserRole::OPERATOR->value;
        }
        $role = Role::where('slug', $roleSlug)->first();
        
        return User::factory()->create([
            'role_id' => $role->id,
            'status' => $status,
        ]);
    }
    
    private function createScan(User $initiator, Repository $repository)
    {
        return Scan::create([
            'uuid' => (string) Str::uuid(),
            'repository_id' => $repository->id,
            'name' => 'Static Code Analysis',
            'description' => 'Vulnerability scan',
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'target' => $repository->name,
            'status' => ScanStatus::QUEUED,
            'progress' => 0,
            'created_by' => $initiator->id,
        ]);
    }

    public function test_scan_initiator_receives_notification()
    {
        $initiator = $this->createUser();
        $repository = Repository::factory()->create(['created_by' => $initiator->id]);
        $scan = $this->createScan($initiator, $repository);

        $context = new NotificationContext(scan: $scan, repository: $repository);
        $recipients = NotificationRouter::resolve(NotificationType::SCAN_COMPLETED, $context);

        $this->assertCount(1, $recipients);
        $this->assertEquals($initiator->id, $recipients->first()->id);
    }

    public function test_repository_owner_receives_notification()
    {
        $owner = $this->createUser();
        $initiator = $this->createUser();
        
        $repository = Repository::factory()->create(['created_by' => $owner->id]);
        $scan = $this->createScan($initiator, $repository);

        $context = new NotificationContext(scan: $scan, repository: $repository);
        $recipients = NotificationRouter::resolve(NotificationType::SCAN_COMPLETED, $context);

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->contains('id', $owner->id));
        $this->assertTrue($recipients->contains('id', $initiator->id));
    }

    public function test_administrator_receives_configured_administrative_alerts()
    {
        $admin = $this->createUser(UserRole::ADMINISTRATOR->value);
        $initiator = $this->createUser();
        
        $repository = Repository::factory()->create(['created_by' => $initiator->id]);
        $scan = $this->createScan($initiator, $repository);

        $context = new NotificationContext(scan: $scan, repository: $repository);
        $recipients = NotificationRouter::resolve(NotificationType::SCAN_FAILED, $context);

        $this->assertCount(2, $recipients);
        $this->assertTrue($recipients->contains('id', $admin->id));
        $this->assertTrue($recipients->contains('id', $initiator->id));
    }

    public function test_duplicate_recipient_sources_result_in_one_notification()
    {
        $adminInitiator = $this->createUser(UserRole::ADMINISTRATOR->value);

        $repository = Repository::factory()->create(['created_by' => $adminInitiator->id]);
        $scan = $this->createScan($adminInitiator, $repository);

        $context = new NotificationContext(scan: $scan, repository: $repository);
        $recipients = NotificationRouter::resolve(NotificationType::SCAN_FAILED, $context);

        $this->assertCount(1, $recipients);
        $this->assertEquals($adminInitiator->id, $recipients->first()->id);
    }

    public function test_inactive_users_are_excluded()
    {
        $initiator = $this->createUser(null, UserStatus::SUSPENDED);
        $repository = Repository::factory()->create(['created_by' => $initiator->id]);
        $scan = $this->createScan($initiator, $repository);

        $context = new NotificationContext(scan: $scan, repository: $repository);
        $recipients = NotificationRouter::resolve(NotificationType::SCAN_COMPLETED, $context);

        $this->assertCount(0, $recipients);
    }
}
