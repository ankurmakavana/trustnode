<?php

namespace App\Services\Repository;

interface RepositoryProviderInterface
{
    /**
     * Validate credentials/token and connectivity to the repository.
     */
    public function validateAccess(string $url, ?string $token = null): bool;

    /**
     * Fetch repository metadata (branch, visibility, name, etc.).
     */
    public function fetchMetadata(string $url, ?string $token = null): array;

    /**
     * Clone the repository to the local path.
     */
    public function clone(string $url, ?string $token, string $destinationPath): bool;
}
