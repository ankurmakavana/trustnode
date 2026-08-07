<?php

namespace App\Services\Import\Adapters;

interface ImportAdapterInterface
{
    /**
     * Parse the given file content or scanner input and return a normalized array structure.
     *
     * The returned array MUST contain keys:
     * - 'assets': array of normalized assets
     * - 'findings': array of normalized findings
     * - 'scans': array of normalized scan details
     *
     * @param  string  $content  Raw file content or payload
     * @return array{assets: array, findings: array, scans: array}
     */
    public function parse(string $content): array;
}
