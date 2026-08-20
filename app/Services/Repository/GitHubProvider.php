<?php

namespace App\Services\Repository;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class GitHubProvider implements RepositoryProviderInterface
{
    /**
     * Parse owner and repository name from GitHub URL.
     */
    public function parseUrl(string $url): ?array
    {
        // Matches https://github.com/owner/repo or git@github.com:owner/repo
        if (preg_match('/github\.com[\/:]([\w.\-]+)\/([\w.\-]+?)(?:\.git)?(?:[\/\s#?]|$)/i', $url, $matches)) {
            return [
                'owner' => $matches[1],
                'repo' => $matches[2],
            ];
        }

        return null;
    }

    /**
     * Build secure git HTTPS URL with embedded token if credentials exist.
     * The token is never logged — it only exists transiently in the process args.
     */
    protected function buildSecureGitUrl(string $url, ?string $token): string
    {
        $parsed = $this->parseUrl($url);
        if (! $parsed) {
            return $url;
        }

        $owner = $parsed['owner'];
        $repo = $parsed['repo'];

        if ($token) {
            // GitHub accepts: https://x-access-token:<PAT>@github.com/owner/repo.git
            // or https://<PAT>@github.com/owner/repo.git (classic PATs)
            // Using x-access-token form handles both classic and fine-grained PATs.
            return "https://x-access-token:{$token}@github.com/{$owner}/{$repo}.git";
        }

        return "https://github.com/{$owner}/{$repo}.git";
    }

    /**
     * Validate connectivity to the repository using GitHub REST API.
     *
     * Returns ['valid' => bool, 'message' => string]
     */
    public function validateAccess(string $url, ?string $token = null): bool
    {
        $parsed = $this->parseUrl($url);
        if (! $parsed) {
            Log::warning('RepositoryProvider: Invalid GitHub URL format', ['url' => $url]);

            return false;
        }

        $owner = $parsed['owner'];
        $repo = $parsed['repo'];

        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}";
        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: TrustNode-Security-Scanner/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if ($token) {
            // Never log the token itself
            $headers[] = 'Authorization: Bearer '.trim($token);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $responseBody = @file_get_contents($apiUrl, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 0 Unknown';

        // Extract status code
        preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $statusMatch);
        $statusCode = (int) ($statusMatch[1] ?? 0);

        if ($statusCode === 200) {
            return true;
        }

        // Log technical detail without token
        Log::info('RepositoryProvider: GitHub API validation failed', [
            'url' => $url,
            'owner' => $owner,
            'repo' => $repo,
            'has_token' => $token !== null,
            'status' => $statusCode,
        ]);

        return false;
    }

    /**
     * Fetch repository metadata via GitHub REST API.
     */
    public function fetchMetadata(string $url, ?string $token = null): array
    {
        $parsed = $this->parseUrl($url);
        if (! $parsed) {
            throw new \InvalidArgumentException("Invalid GitHub URL: {$url}");
        }

        $owner = $parsed['owner'];
        $repo = $parsed['repo'];
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}";

        $headers = [
            'Accept: application/vnd.github+json',
            'User-Agent: TrustNode-Security-Scanner/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        if ($token) {
            $headers[] = 'Authorization: Bearer '.trim($token);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($apiUrl, false, $context);
        $statusLine = $http_response_header[0] ?? 'HTTP/1.1 0 Unknown';
        preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $statusLine, $statusMatch);
        $statusCode = (int) ($statusMatch[1] ?? 0);

        if ($statusCode !== 200 || $body === false) {
            throw new \RuntimeException(
                "GitHub API returned HTTP {$statusCode} for {$owner}/{$repo}. ".
                'Ensure the repository exists and credentials are valid.'
            );
        }

        $data = json_decode($body, true);
        if (! $data) {
            throw new \RuntimeException("GitHub API returned invalid JSON for {$owner}/{$repo}.");
        }

        return [
            'name' => "{$owner}/{$repo}",
            'owner' => $owner,
            'repo' => $repo,
            'repo' => $repo,
            'default_branch' => $data['default_branch'] ?? 'main',
            'visibility' => $data['private'] ? 'private' : 'public',
            'description' => $data['description'] ?? null,
        ];
    }

    /**
     * Clone the repository to the local path using git.
     * Token is passed via process args — never written to disk or logs.
     */
    public function clone(string $url, ?string $token, string $destinationPath): bool
    {
        $secureUrl = $this->buildSecureGitUrl($url, $token);

        // Execute clone using process argument array (no shell interpolation)
        $process = new Process(['git', 'clone', '--depth', '1', $secureUrl, $destinationPath]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('RepositoryProvider: git clone failed', [
                'url' => $url,
                'has_token' => $token !== null,
                // Do NOT log secureUrl or token
            ]);
        }

        return $process->isSuccessful();
    }
}
