<?php

namespace App\Services\Scan\Scanners;

interface ScannerInterface
{
    /**
     * Scan file content for vulnerabilities.
     *
     * @param string $content
     * @param array $lines
     * @param string $relativePath
     * @param string $repositoryUrl
     * @return \App\DTOs\Import\NormalizedFinding[]
     */
    public function scan(string $content, array $lines, string $relativePath, string $repositoryUrl): array;
}
