<?php

namespace App\Services\Scan\Scanners;

class SecretScanner extends AbstractRegexScanner
{
    protected function getRules(): array
    {
        return [
            [
                'id' => 'SEC-SECRET-AWS',
                'title' => 'Hardcoded AWS Access Key',
                'severity' => 'critical',
                'category' => 'Secret',
                'regex' => '/(A3T[A-Z0-9]|AKIA|AGPA|AIDA|AROA|AIPA|ANPA|ANVA|ASIA)[A-Z0-9]{16}/',
                'description' => 'A hardcoded AWS access key ID was discovered. If exposed, this allows unauthorized access to your AWS resources.',
                'remediation' => 'Revoke the AWS access key immediately and replace it with a dynamically retrieved credential from AWS Secrets Manager.',
            ],
            [
                'id' => 'SEC-SECRET-GITHUB',
                'title' => 'Hardcoded GitHub Token',
                'severity' => 'critical',
                'category' => 'Secret',
                'regex' => '/(ghp|gho|ghu|ghs|ghr)_[a-zA-Z0-9]{36}/',
                'description' => 'A GitHub Personal Access Token or OAuth token was found.',
                'remediation' => 'Revoke the token in GitHub settings and replace it with a dynamically injected secret.',
            ],
            [
                'id' => 'SEC-SECRET-GITLAB',
                'title' => 'Hardcoded GitLab Token',
                'severity' => 'critical',
                'category' => 'Secret',
                'regex' => '/glpat-[a-zA-Z0-9\-]{20,}/',
                'description' => 'A GitLab Personal Access Token was found.',
                'remediation' => 'Revoke the token in GitLab and replace it with a dynamically injected secret.',
            ],
            [
                'id' => 'SEC-SECRET-STRIPE',
                'title' => 'Hardcoded Stripe API Key',
                'severity' => 'critical',
                'category' => 'Secret',
                'regex' => '/(sk_live|rk_live)_[a-zA-Z0-9]{24,}/',
                'description' => 'A Stripe live secret key or restricted key was found.',
                'remediation' => 'Roll the Stripe key in the Stripe Dashboard immediately.',
            ],
            [
                'id' => 'SEC-SECRET-SLACK',
                'title' => 'Hardcoded Slack Token',
                'severity' => 'high',
                'category' => 'Secret',
                'regex' => '/xox[baprs]-[a-zA-Z0-9\-]{10,}/',
                'description' => 'A Slack API token was discovered.',
                'remediation' => 'Revoke the token via Slack App settings.',
            ],
            [
                'id' => 'SEC-SECRET-GCP',
                'title' => 'Hardcoded GCP API Key',
                'severity' => 'high',
                'category' => 'Secret',
                'regex' => '/AIza[0-9A-Za-z\-_]{35}/',
                'description' => 'A Google Cloud Platform API key was discovered.',
                'remediation' => 'Restrict or regenerate the API key in the Google Cloud Console.',
            ],
            [
                'id' => 'SEC-SECRET-PRIVATE-KEY',
                'title' => 'Hardcoded Private Key',
                'severity' => 'critical',
                'category' => 'Secret',
                'regex' => '/-----BEGIN (RSA|OPENSSH|DSA|EC|PGP) PRIVATE KEY-----/',
                'description' => 'A private cryptographic key is hardcoded in the repository.',
                'remediation' => 'Store private keys securely in a KMS or secret vault.',
            ],
            [
                'id' => 'SEC-SECRET-JWT',
                'title' => 'Hardcoded JWT Token',
                'severity' => 'high',
                'category' => 'Secret',
                'regex' => '/\beyJ[a-zA-Z0-9-_]+\.[a-zA-Z0-9-_]+\.[a-zA-Z0-9-_]+\b/',
                'description' => 'A JSON Web Token (JWT) was found hardcoded. It may grant unauthorized access.',
                'remediation' => 'Remove hardcoded JWTs. Authenticate dynamically.',
            ],
            [
                'id' => 'SEC-SECRET-GENERIC',
                'title' => 'Exposed Private Token or Secret',
                'severity' => 'high',
                'category' => 'Secret',
                'regex' => '/(secret|token|password|passwd|api_key|apikey|private_key)\s*[:=]\s*["\'\s]*([a-zA-Z0-9_\-\.\/+=]{20,})["\'\s]*/i',
                'description' => 'An API token, private key, or password was found hardcoded in the source code.',
                'remediation' => 'Move hardcoded credentials to configuration environment variables (.env) or a credential vault.',
            ],
        ];
    }

    protected function isValidMatch(array $rule, string $matchedText): bool
    {
        if ($rule['id'] === 'SEC-SECRET-GENERIC') {
            if (preg_match('/[:=]\s*["\'\s]*([a-zA-Z0-9_\-\.\/+=]{20,})["\'\s]*/i', $matchedText, $m)) {
                $value = $m[1];

                $lower = strtolower($value);
                if (
                    str_contains($lower, 'example') ||
                    str_contains($lower, 'placeholder') ||
                    str_contains($lower, 'changeme') ||
                    str_contains($lower, 'test') ||
                    str_contains($lower, 'null') ||
                    str_contains($lower, 'your_')
                ) {
                    return false;
                }

                // Check UUID
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                    return false;
                }
                // Check hex hashes (md5/sha1/sha256)
                if (preg_match('/^[0-9a-f]{32}$|^[0-9a-f]{40}$|^[0-9a-f]{64}$/i', $value)) {
                    return false;
                }

                // Entropy validation
                if ($this->calculateShannonEntropy($value) < 3.5) {
                    return false;
                }
            }
        }

        return true;
    }

    private function calculateShannonEntropy(string $str): float
    {
        $len = strlen($str);
        if ($len === 0) return 0.0;

        $counts = count_chars($str, 1);
        $entropy = 0.0;

        foreach ($counts as $count) {
            $p = $count / $len;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }
}
