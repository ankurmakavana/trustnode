<?php

namespace App\Services\Repository;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class GitHubProvider implements RepositoryProviderInterface
{
    /**
     * Parse owner and repository name from GitHub URL.
     */
    public function parseUrl(string $url): ?array
    {
        // Matches https://github.com/owner/repo or git@github.com:owner/repo
        if (preg_match('/github\.com[\/:][^\/]+\/[^\/\.\?#]+/i', $url, $matches)) {
            $parts = explode('/', str_replace(':', '/', str_replace('git@', '', $matches[0])));
            if (count($parts) >= 3) {
                return [
                    'owner' => $parts[1],
                    'repo' => preg_replace('/\.git$/', '', $parts[2]),
                ];
            }
        }
        return null;
    }

    /**
     * Build secure repository git URL with embedded token if credentials exist.
     */
    protected function buildSecureUrl(string $url, ?string $token): string
    {
        $parsed = $this->parseUrl($url);
        if (!$parsed) {
            return $url;
        }

        if ($token) {
            // Clean token from any trailing/leading whitespaces
            $token = trim($token);
            return "https://{$token}@github.com/{$parsed['owner']}/{$parsed['repo']}.git";
        }

        return "https://github.com/{$parsed['owner']}/{$parsed['repo']}.git";
    }

    /**
     * Validate connectivity to the repository.
     */
    public function validateAccess(string $url, ?string $token = null): bool
    {
        $secureUrl = $this->buildSecureUrl($url, $token);
        
        // Run git ls-remote to check connectivity securely without checking out the entire repo
        $process = new Process(['git', 'ls-remote', $secureUrl]);
        $process->setTimeout(15);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Fetch repository metadata (branch, visibility, name, etc.).
     */
    public function fetchMetadata(string $url, ?string $token = null): array
    {
        $parsed = $this->parseUrl($url);
        if (!$parsed) {
            throw new \InvalidArgumentException("Invalid GitHub URL: {$url}");
        }

        $secureUrl = $this->buildSecureUrl($url, $token);

        // Fetch default branch
        $process = new Process(['git', 'ls-remote', '--symref', $secureUrl, 'HEAD']);
        $process->setTimeout(15);
        $process->run();

        $defaultBranch = 'main';
        if ($process->isSuccessful()) {
            $output = $process->getOutput();
            // Expected line: ref: refs/heads/main HEAD
            if (preg_match('/ref:\s+refs\/heads\/([^\s]+)\s+HEAD/', $output, $matches)) {
                $defaultBranch = $matches[1];
            }
        }

        return [
            'name' => "{$parsed['owner']}/{$parsed['repo']}",
            'owner' => $parsed['owner'],
            'repo' => $parsed['repo'],
            'default_branch' => $defaultBranch,
            'visibility' => $token ? 'private' : 'public',
        ];
    }

    /**
     * Clone the repository to the local path.
     */
    public function clone(string $url, ?string $token, string $destinationPath): bool
    {
        $secureUrl = $this->buildSecureUrl($url, $token);

        // Execute clone securely using process argument array
        $process = new Process(['git', 'clone', '--depth', '1', $secureUrl, $destinationPath]);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful();
    }
}
