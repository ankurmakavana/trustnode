<?php

namespace Tests\Feature;

use App\Jobs\ScanLocalJob;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LocalScanEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_to_end_local_scan_flow_completes_and_persists_findings_and_report(): void
    {
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $role = Role::where('slug', 'administrator')->first();
        $organization = \App\Models\Organization::create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);
        $team = \App\Models\Team::create(['name' => 'Default Team', 'slug' => 'default-team', 'organization_id' => $organization->id]);

        $user = User::factory()->create([
            'role_id' => $role->id,
        ]);
        $user->teams()->attach($team);

        // 1. Prepare synthetic code fixture with an obvious security issue (eval command execution)
        $tempZipPath = tempnam(sys_get_temp_dir(), 'e2e_zip_');
        $zip = new ZipArchive();
        $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        // Add sample PHP code with Secret pattern (AWS Key)
        $sampleCode = <<<'PHP'
<?php
// Configuration file with secret
define('AWS_ACCESS_KEY', 'AKIAIOSFODNN72468013');
PHP;
        $zip->addFromString('src/config.php', $sampleCode);
        $zip->close();

        $uploadedFile = new UploadedFile($tempZipPath, 'synthetic-app.zip', 'application/zip', null, true);

        // 2. Submit authenticated upload to POST /api/scans/local
        $response = $this->actingAs($user)
            ->postJson('/api/scans/local', [
                'archive' => $uploadedFile,
                'target' => 'synthetic-app',
            ]);

        $response->assertStatus(201);
        $scanId = $response->json('id');

        $scan = Scan::findOrFail($scanId);
        if ($scan->status->value === 'queued') {
            // If queued asynchronously, execute ScanLocalJob directly to simulate worker processing
            $job = new ScanLocalJob($scan, "local-scans/scan-{$scanId}.zip");
            $job->handle(
                app(\App\Services\Scan\RepositoryScanner::class),
                app(\App\Services\Import\FingerprintService::class)
            );
            $scan->refresh();
        }

        // 4. Verify completed scan status
        $this->assertEquals('completed', $scan->status->value);
        $this->assertEquals(100, $scan->progress);
        $this->assertNotNull($scan->completed_at);

        // 5. Verify persisted findings
        $scopedFindings = Finding::where('scan_id', $scan->id)->get();
        $unscopedFindings = Finding::withoutGlobalScopes()->where('scan_id', $scan->id)->get();
        $this->assertGreaterThan(0, $unscopedFindings->count(), 'Scan should identify findings from the synthetic fixture (unscoped)');
        $this->assertGreaterThan(0, $scopedFindings->count(), 'Scan should identify findings from the synthetic fixture');

        // 6. Verify HTML/PDF Report record creation
        $report = Report::where('options->scan_id', $scan->id)->first();
        $this->assertNotNull($report, 'Report should be automatically generated for completed local scan');
        $this->assertEquals('Ready', $report->status);
        $this->assertStringContainsString('Local Directory Vulnerability Assessment', $report->title);

        // 7. Verify report options contain html content
        $this->assertArrayHasKey('html_report', $report->options);
        $this->assertStringContainsString('Vulnerability Assessment Report', $report->options['html_report']);

        // 8. Assert workspace directory was cleaned up safely
        $workspacePath = storage_path('app/temp-scan-workspace/' . $scan->uuid);
        $this->assertFalse(file_exists($workspacePath), 'Temporary sandbox workspace must be removed');
    }
}
