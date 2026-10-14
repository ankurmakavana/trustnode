<?php

namespace TrustNode\Cli\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class DirectoryWatcher
{
    private LocalDirectoryPackager $packager;

    public function __construct()
    {
        $this->packager = new LocalDirectoryPackager();
    }

    /**
     * Compute a state digest of eligible files in the directory.
     * Maps relative file path -> "mtime:size".
     * Excludes secrets (.env, keys) and ignored paths.
     *
     * @param string $sourceDir
     * @return array<string, string>
     */
    public function getSnapshot(string $sourceDir): array
    {
        $realSource = realpath($sourceDir);
        if (!$realSource || !is_dir($realSource)) {
            return [];
        }

        $snapshot = [];
        $directoryIterator = new RecursiveDirectoryIterator($realSource, FilesystemIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::LEAVES_ONLY);

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $filePath = $fileInfo->getRealPath();
            if (!$filePath) {
                continue;
            }

            // Boundary check: file must be strictly within sourceDir
            if (!str_starts_with($filePath, $realSource . DIRECTORY_SEPARATOR) && $filePath !== $realSource) {
                continue;
            }

            $relativePath = substr($filePath, strlen($realSource) + 1);
            $normalizedRel = str_replace('\\', '/', $relativePath);

            if (!$this->packager->isEligible($normalizedRel, $fileInfo->getSize())) {
                continue;
            }

            $snapshot[$normalizedRel] = $fileInfo->getMTime() . ':' . $fileInfo->getSize();
        }

        ksort($snapshot);
        return $snapshot;
    }

    /**
     * Compare previous snapshot with current snapshot to detect changes.
     *
     * @param array<string, string> $prev
     * @param array<string, string> $current
     * @return array{changed: bool, added: int, modified: int, removed: int}
     */
    public function diff(array $prev, array $current): array
    {
        $added = 0;
        $modified = 0;
        $removed = 0;

        foreach ($current as $path => $state) {
            if (!isset($prev[$path])) {
                $added++;
            } elseif ($prev[$path] !== $state) {
                $modified++;
            }
        }

        foreach ($prev as $path => $state) {
            if (!isset($current[$path])) {
                $removed++;
            }
        }

        $changed = ($added > 0 || $modified > 0 || $removed > 0);

        return [
            'changed' => $changed,
            'added' => $added,
            'modified' => $modified,
            'removed' => $removed,
        ];
    }
}
