<?php

namespace App\Services;

/**
 * Server-side redaction of credential-shaped text.
 *
 * Applied before findings, evidence, logs and reports are persisted or returned, so that
 * client-side masking is never the only protection. Patterns are deliberately conservative:
 * they remove values that follow a sensitive key name or match a known token shape, and leave
 * other text unchanged.
 */
class SecretRedactor
{
    public const PLACEHOLDER = '[REDACTED]';

    private const TOKEN_PATTERNS = [
        '/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----.*?-----END [A-Z0-9 ]*PRIVATE KEY-----/s',
        '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
        '/\bgh[pousr]_[A-Za-z0-9]{20,}\b/',
        '/\bgithub_pat_[A-Za-z0-9_]{22,}\b/',
        '/\bglpat-[A-Za-z0-9_\-]{20,}\b/',
        '/\b(?:sk|rk|pk)_(?:live|test)_[A-Za-z0-9]{10,}\b/',
        '/\bxox[abposr]-[A-Za-z0-9\-]{10,}\b/',
        '/\bAIza[0-9A-Za-z_\-]{35}\b/',
        '/\beyJ[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}\.[A-Za-z0-9_\-]{5,}\b/',
    ];

    private const URL_CREDENTIAL_PATTERN = '/(\b[a-z][a-z0-9+.\-]*:\/\/)[^\s\/:@]+:[^\s\/@]+@/i';

    private const BEARER_PATTERN = '/(\bbearer\s+)[A-Za-z0-9._~+\/\-]+=*/i';

    // Keeps the key name and separator so the finding stays readable, and replaces only the value.
    private const ASSIGNMENT_PATTERN = '/(\b[A-Za-z0-9_.\-]*(?:password|passwd|pwd|secret|token|api[_\-]?key|apikey|access[_\-]?key|private[_\-]?key|client[_\-]?secret|credentials?)[A-Za-z0-9_.\-]*["\']?\s*[:=]\s*)(?:"[^"\r\n]*"|\'[^\'\r\n]*\'|[^\s"\',;#]+)/i';

    public static function redact(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        foreach (self::TOKEN_PATTERNS as $pattern) {
            $text = self::replace($pattern, self::PLACEHOLDER, $text);
        }

        $text = self::replace(self::URL_CREDENTIAL_PATTERN, '$1' . self::PLACEHOLDER . '@', $text);
        $text = self::replace(self::BEARER_PATTERN, '$1' . self::PLACEHOLDER, $text);
        $text = self::replace(self::ASSIGNMENT_PATTERN, '$1' . self::PLACEHOLDER, $text);

        return $text;
    }

    /**
     * Fails closed: if a pattern cannot be applied (for example, a backtracking limit on very large
     * input), the whole text is replaced rather than returned unredacted.
     */
    private static function replace(string $pattern, string $replacement, string $text): string
    {
        $result = preg_replace($pattern, $replacement, $text);

        return $result ?? self::PLACEHOLDER;
    }
}
