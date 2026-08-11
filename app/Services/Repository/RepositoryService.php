<?php

namespace App\Services\Repository;

use App\Models\IntegrationCredential;
use App\Models\Repository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RepositoryService
{
    public GitHubProvider $githubProvider;

    public function __construct(GitHubProvider $githubProvider)
    {
        $this->githubProvider = $githubProvider;
    }

    /**
     * Connect a repository and save it to the database with encrypted credentials.
     */
    public function connect(array $data, int $userId): Repository
    {
        $url = $data['repository_url'];
        $visibility = $data['visibility'] ?? 'public';
        $token = $data['token'] ?? null;

        // 1. Validate repository access first
        $isValid = $this->githubProvider->validateAccess($url, $token);
        if (!$isValid) {
            throw new \RuntimeException("Unable to access repository. Please check URL or credentials.");
        }

        // 2. Fetch metadata
        $meta = $this->githubProvider->fetchMetadata($url, $token);

        // 3. Save credential securely if token is provided
        $credentialId = null;
        if ($visibility === 'private' && $token) {
            // Find existing or create new credential record
            // First we need a dummy integration or save it directly.
            // Since integration table requires integration_id, let's find the github integration.
            $integration = \App\Models\Integration::firstOrCreate(
                ['code' => 'github'],
                [
                    'name' => 'GitHub Provider',
                    'type' => 'vcs',
                    'status' => 'Connected',
                ]
            );

            $credential = IntegrationCredential::create([
                'uuid' => (string) Str::uuid(),
                'integration_id' => $integration->id,
                'key' => 'token',
                'value' => Crypt::encryptString($token),
            ]);
            $credentialId = $credential->id;
        }

        // 4. Save repository record
        return Repository::create([
            'uuid' => (string) Str::uuid(),
            'provider' => 'github',
            'repository_url' => $url,
            'repository_id' => $meta['repo'] ?? null,
            'name' => $meta['name'],
            'visibility' => $visibility,
            'default_branch' => $meta['default_branch'] ?? 'main',
            'integration_credential_id' => $credentialId,
            'status' => 'Connected',
            'created_by' => $userId,
        ]);
    }

    /**
     * Create isolated temporary directory path for checkout.
     */
    public function createTemporaryWorkspace(string $uuid): string
    {
        // Keep temporary directory inside framework storage/app structure to avoid path traversals
        $path = storage_path("app/tmp_scans/{$uuid}");
        File::ensureDirectoryExists($path);
        return $path;
    }

    /**
     * Safely checkout a repository.
     */
    public function checkout(Repository $repository, string $destinationPath): bool
    {
        $token = null;
        if ($repository->credential) {
            $token = Crypt::decryptString($repository->credential->value);
        }

        return $this->githubProvider->clone($repository->repository_url, $token, $destinationPath);
    }

    /**
     * Safely delete temporary checkout directory.
     */
    public function cleanWorkspace(string $workspacePath): void
    {
        // Enforce boundary safety check
        if (Str::startsWith($workspacePath, storage_path('app/tmp_scans/'))) {
            File::deleteDirectory($workspacePath);
        }
    }
}
