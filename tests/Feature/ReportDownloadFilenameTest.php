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
 * The report download's filename is part of an HTTP header. A target name with quotes, spaces, CR/LF or
 * other control characters must produce a well-formed, safe filename. The stored file is faked.
 */
class ReportDownloadFilenameTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        $this->admin = User::factory()->create(['role_id' => Role::where('slug', UserRole::ADMINISTRATOR->value)->firstOrFail()->id]);
        $this->org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $this->org->id])->users()->attach($this->admin->id);
    }

    private function completedReportFor(string $target): Scan
    {
        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'download scan',
            'target' => $target,
            'status' => ScanStatus::COMPLETED,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);

        Storage::disk('local')->put("reports/{$scan->id}.pdf", '%PDF-1.4 test');
        ScanReport::create([
            'scan_id' => $scan->id,
            'requested_by' => $this->admin->id,
            'status' => 'completed',
            'progress' => 100,
            'file_path' => "reports/{$scan->id}.pdf",
        ]);

        return $scan;
    }

    private function dispositionFor(Scan $scan): string
    {
        $response = $this->actingAs($this->admin)->get("/api/scans/{$scan->id}/report/download");
        $response->assertOk();

        return (string) $response->headers->get('Content-Disposition');
    }

    /** The filename token from the header, with any quotes removed. */
    private function filenameFrom(string $disposition): string
    {
        $this->assertMatchesRegularExpression('/^attachment; filename=/', $disposition);

        return trim(substr($disposition, strlen('attachment; filename=')), '"');
    }

    public function test_hostile_target_name_is_reduced_to_a_safe_filename(): void
    {
        $scan = $this->completedReportFor("evil\"; name=x\r\nX-Injected: 1 target/../secret%.php");

        $disposition = $this->dispositionFor($scan);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9.-]+\.pdf$/', $this->filenameFrom($disposition));
        $this->assertStringNotContainsString('X-Injected', $disposition);
        $this->assertStringNotContainsString("\r", $disposition);
        $this->assertStringNotContainsString("\n", $disposition);
    }

    public function test_filename_keeps_the_trustnode_prefix_and_the_date(): void
    {
        $scan = $this->completedReportFor('approved-app');

        $disposition = $this->dispositionFor($scan);

        $filename = $this->filenameFrom($disposition);
        $this->assertStringStartsWith('trustnode-approved-app-scan-', $filename);
        $this->assertStringEndsWith('.pdf', $filename);
    }

    public function test_empty_or_fully_unsafe_target_falls_back_to_repo(): void
    {
        $scan = $this->completedReportFor('"""   ');

        $this->assertStringStartsWith('trustnode-repo-scan-', $this->filenameFrom($this->dispositionFor($scan)));
    }

    public function test_very_long_target_is_capped(): void
    {
        $scan = $this->completedReportFor(str_repeat('a', 300));

        // 80 characters of target, plus the fixed prefix, date and extension.
        $filename = $this->filenameFrom($this->dispositionFor($scan));
        $this->assertLessThanOrEqual(110, strlen($filename));
    }
}
