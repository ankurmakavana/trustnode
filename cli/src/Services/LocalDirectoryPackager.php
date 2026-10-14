<?php

namespace TrustNode\Cli\Services;

use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class LocalDirectoryPackager
{
    /**
     * Packages a local project directory into a secure zip archive.
     * Follows strict security exclusions matching TrustNode policy.
     * 
     * @param string $sourceDir Local folder path to package
     * @param string $outputZipPath Destination zip file path
     * @return array{files_count: int, total_size: int} Summary of packaged archive
     * @throws \RuntimeException
     */
    public function package(string $sourceDir, string $outputZipPath): array
    {
        $realSource = realpath($sourceDir);
        if (!$realSource || !is_dir($realSource)) {
            throw new \RuntimeException("Directory does not exist or is not readable: {$sourceDir}");
        }

        $zip = new ZipArchive();
        $status = $zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($status !== true) {
            throw new \RuntimeException("Failed to create archive at {$outputZipPath}. Code: {$status}");
        }

        $maxFiles = 50000;
        $maxTotalSize = 100 * 1024 * 1024; // 100MB compressed/uncompressed bound for upload
        $maxPerFile = 5 * 1024 * 1024; // 5MB per file

        $filesCount = 0;
        $totalSize = 0;

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

            // Boundary containment check: file must be strictly within sourceDir
            if (!str_starts_with($filePath, $realSource . DIRECTORY_SEPARATOR) && $filePath !== $realSource) {
                continue;
            }

            // Relative path inside the archive
            $relativePath = substr($filePath, strlen($realSource) + 1);
            $normalizedRel = str_replace('\\', '/', $relativePath);

            if (!$this->isEligible($normalizedRel, $fileInfo->getSize())) {
                continue;
            }

            if ($fileInfo->getSize() > $maxPerFile) {
                continue;
            }

            $totalSize += $fileInfo->getSize();
            if ($totalSize > $maxTotalSize) {
                $zip->close();
                @unlink($outputZipPath);
                throw new \RuntimeException("Project directory exceeds maximum allowed upload size (100MB).");
            }

            $filesCount++;
            if ($filesCount > $maxFiles) {
                $zip->close();
                @unlink($outputZipPath);
                throw new \RuntimeException("Project directory contains more than {$maxFiles} files.");
            }

            $zip->addFile($filePath, $normalizedRel);
        }

        $zip->close();

        return [
            'files_count' => $filesCount,
            'total_size' => $totalSize,
        ];
    }

    /**
     * Determines whether a relative file path is safe and eligible for scanning.
     */
    public function isEligible(string $normalizedPath, int $size): bool
    {
        // Traversal rejection
        if (str_contains($normalizedPath, '..') || str_starts_with($normalizedPath, '/') || str_contains($normalizedPath, "\0")) {
            return false;
        }

        $parts = explode('/', $normalizedPath);
        $fileName = end($parts);

        // Excluded directories
        $excludedDirs = ['.git', 'vendor', 'node_modules', 'storage', 'public', '.idea', '.vscode', '.gradle', 'dist', 'build'];
        foreach (array_slice($parts, 0, -1) as $dirPart) {
            if (in_array(strtolower($dirPart), $excludedDirs, true)) {
                return false;
            }
        }

        // Excluded secret files
        if ($fileName === '.env' || str_starts_with($fileName, '.env.') || $fileName === '.htpasswd' || $fileName === '.netrc') {
            return false;
        }

        // Private keys & certificates
        if (preg_match('/\.(pem|key|pkcs12|pfx|kdbx|crt|der)$/i', $fileName)) {
            return false;
        }

        if (preg_match('/^(id_rsa|id_dsa|id_ecdsa|id_ed25519)/i', $fileName)) {
            return false;
        }

        // Binaries and large media
        if (preg_match('/\.(png|jpg|jpeg|gif|ico|zip|tar|gz|exe|bin|pdf|woff|woff2|eot|ttf|mp4|db|sqlite|sqlite3)$/i', $fileName)) {
            return false;
        }

        return true;
    }
}
