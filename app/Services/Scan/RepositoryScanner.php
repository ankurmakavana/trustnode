<?php

namespace App\Services\Scan;

use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Facades\File;

class RepositoryScanner
{
    /**
     * @var \App\Services\Scan\Scanners\ScannerInterface[]
     */
    protected array $scanners = [];

    public function __construct()
    {
        $this->scanners = [
            new \App\Services\Scan\Scanners\SastScanner(),
            new \App\Services\Scan\Scanners\SecretScanner(),
            new \App\Services\Scan\Scanners\ScaScanner(
                new \App\Services\Scan\Dependencies\ComposerLockParser(),
                new \App\Services\Scan\Vulnerability\OsvApiClient()
            ),
        ];
    }

    /**
     * Run scan recursively on the workspace directory.
     */
    public function scan(string $workspacePath, string $repositoryUrl): array
    {
        $findings = [];
        $files = File::allFiles($workspacePath);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();

            // Skip binary or vendor or .git files
            if ($this->shouldSkip($relativePath)) {
                continue;
            }

            // Skip files larger than 5MB to prevent memory exhaustion
            $realPath = $file->getRealPath();
            if (filesize($realPath) > 5 * 1024 * 1024) {
                continue;
            }

            $content = @file_get_contents($realPath);
            if ($content === false) {
                continue;
            }

            $lines = explode("\n", $content);

            foreach ($this->scanners as $scanner) {
                $scannerFindings = $scanner->scan($content, $lines, $relativePath, $repositoryUrl);
                foreach ($scannerFindings as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        return $findings;
    }

    /**
     * Skip binary files, vendor directories, git data, or lock files.
     */
    protected function shouldSkip(string $path): bool
    {
        $skippedPatterns = [
            '/^\.git\//',
            '/^vendor\//',
            '/^node_modules\//',
            '/\.(png|jpg|jpeg|gif|ico|zip|tar|gz|exe|bin|pdf|woff|woff2|eot|ttf|mp4|db|sqlite|sqlite3)$/i',
        ];

        foreach ($skippedPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
