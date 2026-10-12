<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Who may download a scan's report. Same-organization access alone is not enough: the caller also needs
 * the scans.view permission. Cross-organization access is covered by TenantIsolationTest.
 */
class ReportDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $member;
    private User $noPermissionMember;
    private Scan $scan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $org = Organization::factory()->create();
        $team = Team::factory()->create(['organization_id' => $org->id]);

        $this->member = User::factory()->create(['role_id' => Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail()->id]);
        $team->users()->attach($this->member->id);

        // Same organization, but a role with no permissions at all.
        $noAccess = Role::create(['name' => 'No access (test)', 'slug' => 'no-access-test']);
        $this->noPermissionMember = User::factory()->create(['role_id' => $noAccess->id]);
        $team->users()->attach($this->noPermissionMember->id);

        $this->scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'authorization scan',
            'target' => 'approved-app',
            'status' => ScanStatus::COMPLETED,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'organization_id' => $org->id,
            'created_by' => $this->member->id,
        ]);

        Storage::disk('local')->put("reports/{$this->scan->id}.pdf", '%PDF-1.4 test');
        ScanReport::create([
            'scan_id' => $this->scan->id,
            'requested_by' => $this->member->id,
            'status' => 'completed',
            'progress' => 100,
            'file_path' => "reports/{$this->scan->id}.pdf",
        ]);
    }

    public function test_a_member_with_the_scan_permission_can_download_the_report()
    {
        $this->actingAs($this->member)
            ->get("/api/scans/{$this->scan->id}/report/download")
            ->assertOk();
    }

    public function test_a_same_organization_user_without_the_scan_permission_is_forbidden()
    {
        $this->actingAs($this->noPermissionMember)
            ->get("/api/scans/{$this->scan->id}/report/download")
            ->assertForbidden();
    }

    public function test_an_unauthenticated_request_is_rejected()
    {
        $this->getJson("/api/scans/{$this->scan->id}/report/download")
            ->assertUnauthorized();
    }
}
