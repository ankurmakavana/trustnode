<?php

namespace Tests\Feature;

use App\Jobs\ScanLocalJob;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ArchiveSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function createTestArchive(string $name, callable $addFiles)
    {
        $path = storage_path('app/temp/' . $name . '.zip');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $addFiles($zip);
        $zip->close();
        return $path;
    }

    protected function runScanJob(string $zipPath)
    {
        // Many tests use roles, so we should create a Role first or pass role_id=1
        // to prevent Integrity constraint violations.
        $user = User::factory()->create([
            'role_id' => \App\Models\Role::firstOrCreate(['name' => 'admin', 'slug' => 'admin'])->id ?? 1
        ]);
        $this->actingAs($user);

        $scan = Scan::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Scan',
            'target' => 'test-target',
            'status' => 'queued',
            'type' => 'local',
            'engine' => 'localscanner',
            'created_by' => $user->id
        ]);

        $storagePath = 'local_scans/' . $scan->uuid . '.zip';
        Storage::disk('local')->put($storagePath, file_get_contents($zipPath));

        $job = new ScanLocalJob($scan, $storagePath);
        try {
            $job->handle(app(\App\Services\Scan\RepositoryScanner::class), app(\App\Services\Import\FingerprintService::class));
        } catch (\Exception $e) {
            // expected on failure
        }
        
        $scan->refresh();
        
        // Assert workspace cleaned up
        $workspacePath = storage_path('app/temp-scan-workspace/'.$scan->uuid);
        $this->assertFalse(file_exists($workspacePath), 'Workspace should be cleaned up');
        
        return $scan;
    }

    public function test_valid_archive_succeeds()
    {
        $path = $this->createTestArchive('valid', function ($zip) {
            $zip->addFromString('test.txt', 'hello world');
            $zip->addFromString('src/app/Service.php', '<?php class Service {}');
            $zip->addFromString('docs/version..txt', '1.0');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('completed', $scan->status->value);
    }

    public function test_zip_slip_unix_rejected()
    {
        $path = $this->createTestArchive('slip_unix', function ($zip) {
            $zip->addFromString('../../outside.txt', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_zip_slip_win_rejected()
    {
        $path = $this->createTestArchive('slip_win', function ($zip) {
            $zip->addFromString('..\\..\\outside.txt', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_absolute_unix_rejected()
    {
        $path = $this->createTestArchive('abs_unix', function ($zip) {
            $zip->addFromString('/tmp/outside.txt', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_absolute_win_rejected()
    {
        $path = $this->createTestArchive('abs_win', function ($zip) {
            $zip->addFromString('C:\outside.txt', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_unc_path_rejected()
    {
        $path = $this->createTestArchive('unc', function ($zip) {
            $zip->addFromString('\\\\server\share\outside.txt', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_mixed_separators_rejected()
    {
        $path = $this->createTestArchive('mixed', function ($zip) {
            $zip->addFromString('..\\foo/../../bar', 'evil');
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_file_count_limit_rejected()
    {
        $path = $this->createTestArchive('count', function ($zip) {
            for ($i = 0; $i < 50001; $i++) {
                // adding from string in memory could be slow for 50001 files,
                // but the zip stat check doesn't need to actually have data, 
                // just entries. Let's add empty files.
                $zip->addFromString("file{$i}.txt", "");
            }
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }

    public function test_file_size_limit_rejected()
    {
        $path = $this->createTestArchive('oversized', function ($zip) {
            // create 5.1 mb file
            $data = str_repeat('a', (5 * 1024 * 1024) + 1024);
            $zip->addFromString('big.txt', $data);
        });

        $scan = $this->runScanJob($path);
        $this->assertEquals('failed', $scan->status->value);
    }
}
