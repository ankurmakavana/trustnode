<?php

namespace App\Jobs;

use App\Enums\Finding\FindingSeverity;
use App\Enums\Finding\FindingStatus;
use App\Models\Finding;
use App\Models\Report;
use App\Models\Scan;
use App\Services\Import\FingerprintService;
use App\Services\Scan\RepositoryScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\TenantAwareJob;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Notifications\ScanCompletedNotification;
use App\Notifications\ScanFailedNotification;

class ScanLocalJob implements ShouldQueue, TenantAwareJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Scan $scan;
    protected string $archivePath;

    public function __construct(Scan $scan, string $archivePath)
    {
        $this->scan = $scan;
        $this->archivePath = $archivePath;
    }

    public function handle(
        RepositoryScanner $scanner,
        FingerprintService $fingerprintService
    ) {
        Log::info('ScanLocalJob starting for scan '.$this->scan->id);

        $this->scan->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $workspacePath = storage_path('app/temp-scan-workspace/'.$this->scan->uuid);

        try {
            $this->scan->update(['progress' => 10]);

            // 1. Validate and safely extract archive
            $zipPath = Storage::disk('local')->path($this->archivePath);

            if (!file_exists($workspacePath)) {
                mkdir($workspacePath, 0755, true);
            }

            $zip = new \ZipArchive;
            $res = $zip->open($zipPath);
            if ($res !== TRUE) {
                throw new \RuntimeException('Failed to open local scan archive. Error code: ' . $res);
            }

            $maxFiles = 50000;
            $maxTotalUncompressed = 200 * 1024 * 1024; // 200 MB
            $maxPerFile = 5 * 1024 * 1024; // 5 MB

            if ($zip->numFiles > $maxFiles) {
                $zip->close();
                throw new \RuntimeException('Archive contains too many files. Limit is ' . $maxFiles);
            }

            $totalUncompressed = 0;
            $safeEntries = [];

            // Pre-extraction validation pass
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) continue;

                $name = $stat['name'];
                $size = $stat['size'];

                // Symlinks or special files could be identified via external attributes.
                // ZipArchive doesn't directly expose symlink via stat() cross-platform safely,
                // but usually symlinks have size > 0 and specific external attributes.
                // We'll reject them if we detect them, but rely on path validation primary.
                $extAttr = isset($stat['ext']) ? $stat['ext'] : 0;
                $isSymlink = ($extAttr & 0120000) === 0120000; // UNIX symlink flag

                if ($isSymlink) {
                    $zip->close();
                    throw new \RuntimeException("Archive contains symlink entries which are not permitted: {$name}");
                }

                if ($size > $maxPerFile) {
                    $zip->close();
                    throw new \RuntimeException("Archive contains an oversized file exceeding {$maxPerFile} bytes: {$name}");
                }

                $totalUncompressed += $size;
                if ($totalUncompressed > $maxTotalUncompressed) {
                    $zip->close();
                    throw new \RuntimeException("Archive exceeds total uncompressed size limit of {$maxTotalUncompressed} bytes.");
                }

                // Check for Zip Slip / Path Traversal
                if (
                    str_contains($name, '../') ||
                    str_contains($name, '..\\') ||
                    str_starts_with($name, '/') ||
                    str_starts_with($name, '\\') ||
                    preg_match('/^[a-zA-Z]:\\\\/', $name) || // Windows Drive
                    str_starts_with($name, '\\\\') || // Windows UNC
                    str_contains($name, "\0")
                ) {
                    $zip->close();
                    throw new \RuntimeException("Archive contains illegal path traversal or absolute path: {$name}");
                }

                $safeEntries[] = $name;
            }

            // Realpath of workspace for strict post-write containment check
            $realWorkspacePath = realpath($workspacePath);
            if (!$realWorkspacePath) {
                $zip->close();
                throw new \RuntimeException("Failed to resolve absolute workspace path.");
            }

            // Extraction pass
            foreach ($safeEntries as $name) {
                $destPath = $workspacePath . DIRECTORY_SEPARATOR . $name;

                // Lexical normalization check before writing (if needed), but we've rejected '..'

                // Check if directory
                if (str_ends_with($name, '/') || str_ends_with($name, '\\')) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                    continue;
                }

                // Ensure parent directory exists
                $parentDir = dirname($destPath);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }

                // Extract single file safely using stream
                $fp = $zip->getStream($name);
                if (!$fp) continue;

                $destFp = fopen($destPath, 'wb');
                if (!$destFp) {
                    fclose($fp);
                    continue;
                }

                // Write with bounds checking to prevent mismatch between stat size and actual stream size
                $writtenBytes = 0;
                while (!feof($fp)) {
                    $chunk = fread($fp, 8192);
                    if ($chunk === false) break;

                    $writtenBytes += strlen($chunk);
                    if ($writtenBytes > $maxPerFile) {
                        fclose($destFp);
                        fclose($fp);
                        $zip->close();
                        throw new \RuntimeException("Stream size exceeded per-file limit during extraction of {$name}");
                    }

                    fwrite($destFp, $chunk);
                }

                fclose($destFp);
                fclose($fp);

                // Post-write realpath containment verification
                $realDestPath = realpath($destPath);
                if (!$realDestPath || !str_starts_with($realDestPath, $realWorkspacePath)) {
                    $zip->close();
                    throw new \RuntimeException("Path containment verification failed for extracted file: {$name}");
                }
            }

            $zip->close();

            $this->scan->update(['progress' => 20]);

            // Count files scanned
            $filesScanned = 0;
            if (is_dir($workspacePath)) {
                $directoryIterator = new \RecursiveDirectoryIterator($workspacePath, \FilesystemIterator::SKIP_DOTS);
                $iterator = new \RecursiveIteratorIterator($directoryIterator);
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $filesScanned++;
                    }
                }
            }
            $this->scan->update(['files_scanned' => $filesScanned]);

            $this->scan->update(['progress' => 50]);

            // 2. Perform static analysis scan
            $findings = $scanner->scan($workspacePath, $this->scan->target);

            $this->scan->update(['progress' => 80]);

            // 4. Save and deduplicate findings
            $savedFindings = [];
            $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0];

            foreach ($findings as $dto) {
                $fingerprint = $fingerprintService->generate($dto, null);
                $dto->fingerprint = $fingerprint;

                $severityVal = strtolower($dto->severity ?? 'low');
                if (isset($severityCounts[$severityVal])) {
                    $severityCounts[$severityVal]++;
                }

                $severity = FindingSeverity::LOW;
                foreach (FindingSeverity::cases() as $case) {
                    if (strtolower($case->value) === $severityVal) {
                        $severity = $case;
                        break;
                    }
                }

                $identityHash = hash('sha256', ($this->scan->created_by ?? '') . '|' . $fingerprint . '|||' . $this->scan->local_project_id);

                $findingIdentity = \App\Models\FindingIdentity::firstOrCreate(
                    [
                        'identity_hash' => $identityHash,
                    ],
                    [
                        'fingerprint' => $fingerprint,
                        'local_project_id' => $this->scan->local_project_id,
                        'created_by' => $this->scan->created_by,
                        'first_seen_at' => $this->scan->started_at ?? now(),
                        'last_seen_at' => $this->scan->started_at ?? now(),
                    ]
                );

                $findingModel = Finding::updateOrCreate(
                    [
                        'fingerprint' => $fingerprint,
                        'scan_id' => $this->scan->id,
                    ],
                    [
                        'title' => $dto->title,
                        'asset_id' => null,
                        'finding_identity_id' => $findingIdentity->id,
                        'severity' => $severity,
                        'status' => FindingStatus::OPEN,
                        'category' => $dto->category ?? 'SAST',
                        'cwe' => $dto->cwe ?? null,
                        'description' => $dto->description ?? '',
                        'remediation' => $dto->remediation ?? '',
                        'technical_details' => $dto->technicalDetails ?? '',
                        'evidence' => $dto->evidence ?? '',
                        'url' => $dto->url ?? $dto->path ?? '',
                        'scanner' => $dto->scanner ?? 'RepositoryScanner',
                        'created_by' => $this->scan->created_by,
                    ]
                );

                $savedFindings[] = $findingModel;
            }

            $this->scan->update(['progress' => 90]);

            // 5. Generate HTML report
            $reportTitle = 'Local Directory Vulnerability Assessment - '.$this->scan->target;
            $viewName = view()->exists('reports.local_scan') ? 'reports.local_scan' : 'reports.repository_scan';
            $htmlContent = view($viewName, [
                'target' => $this->scan->target,
                'scan' => $this->scan,
                'repository' => null,
                'findings' => $savedFindings,
                'severityCounts' => $severityCounts,
            ])->render();

            $report = Report::create([
                'uuid' => (string) Str::uuid(),
                'title' => $reportTitle,
                'type' => 'sast',
                'status' => 'Ready',
                'options' => [
                    'scan_id' => $this->scan->id,
                    'severity_counts' => $severityCounts,
                    'html_report' => $htmlContent,
                ],
                'created_by' => $this->scan->created_by,
            ]);

            // 6. Complete scan
            $duration = now()->diffInSeconds($this->scan->started_at);
            $this->scan->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_at' => now(),
                'duration' => $duration,
            ]);

            app(\App\Services\Finding\FindingLifecycleService::class)->process($this->scan);

            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_COMPLETED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan),
                new ScanCompletedNotification($this->scan, array_sum($severityCounts))
            );

        } catch (\Throwable $e) {
            Log::error('ScanLocalJob failed: '.$e->getMessage(), ['exception' => $e]);
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            \App\Services\Notification\NotificationRouter::dispatch(
                \App\Enums\Notification\NotificationType::SCAN_FAILED,
                new \App\Services\Notification\NotificationContext(scan: $this->scan),
                new ScanFailedNotification($this->scan, 'Scan failed during execution.')
            );
        } finally {
            if (file_exists($workspacePath)) {
                $this->removeDirectory($workspacePath);
            }
            if (file_exists($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
                    $this->removeDirectory($dir. DIRECTORY_SEPARATOR .$object);
                else
                    @unlink($dir. DIRECTORY_SEPARATOR .$object);
            }
        }
        @rmdir($dir);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ScanLocalJob failed fatally: '.$exception->getMessage(), ['exception' => $exception]);

        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
        ]);

        \App\Services\Notification\NotificationRouter::dispatch(
            \App\Enums\Notification\NotificationType::SCAN_FAILED,
            new \App\Services\Notification\NotificationContext(scan: $this->scan),
            new ScanFailedNotification($this->scan, 'Scan failed fatally.')
        );
    }

    public function getTenantId(): ?int
    {
        return $this->scan->created_by;
    }
}
