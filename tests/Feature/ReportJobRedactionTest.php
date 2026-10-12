<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanStatus;
use App\Enums\Scan\ScanType;
use App\Enums\UserRole;
use App\Jobs\GenerateScanReport;
use App\Models\Finding;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Scan;
use App\Models\ScanReport;
use App\Models\Team;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Persisted report generation with the renderer replaced by a recorder. Checks what the report template
 * receives (no raw credential, even from a row stored before redaction existed), the stored file, and the
 * report status. The PDF itself is not rendered. Values are synthetic.
 */
class ReportJobRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_AWS = 'AKIAFAKEFAKEFAKEFAKE';
    private const FAKE_PASSWORD = 'FAKEdbpass123';

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

    protected function tearDown(): void
    {
        Pdf::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_report_template_receives_no_raw_credential_and_the_report_is_stored(): void
    {
        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'report scan',
            'target' => 'approved-app',
            'status' => ScanStatus::COMPLETED,
            'type' => ScanType::REPOSITORY,
            'engine' => ScanEngine::REPOSITORY_SCANNER,
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
        ]);

        $finding = Finding::factory()->create([
            'scan_id' => $scan->id,
            'category' => 'Injection',
            'severity' => 'high',
            'created_by' => $this->admin->id,
        ]);
        // Written by older code, so the model hook never redacted it.
        DB::table('findings')->where('id', $finding->id)->update([
            'evidence' => 'aws=' . self::FAKE_AWS . ' DB_PASSWORD=' . self::FAKE_PASSWORD,
            'technical_details' => 'token=' . self::FAKE_AWS,
        ]);

        $report = ScanReport::create([
            'scan_id' => $scan->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
            'progress' => 0,
        ]);

        $captured = null;
        Pdf::shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use (&$captured) {
            $captured = $data;

            // The facade's return type is Barryvdh\DomPDF\PDF, so the stand-in must be that class.
            $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdf->shouldReceive('output')->andReturn('%PDF-1.4 synthetic report');

            return $pdf;
        });

        // The queue listener sets the tenant context from getTenantId() before a job runs. A direct
        // handle() call bypasses that listener, so the context is set here.
        $job = new GenerateScanReport($report);
        \App\Services\TenantContext::setUserId($job->getTenantId());
        $job->handle();

        $this->assertNotNull($captured, 'The report template must be rendered.');
        $rendered = json_encode($captured['findings']->map(fn ($f) => [$f->evidence, $f->technical_details])->all());
        $this->assertStringNotContainsString(self::FAKE_AWS, $rendered);
        $this->assertStringNotContainsString(self::FAKE_PASSWORD, $rendered);

        $report->refresh();
        $this->assertSame('completed', $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertTrue(Storage::disk('local')->exists($report->file_path), 'The generated report must be stored.');
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }
}
