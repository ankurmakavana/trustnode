<?php

namespace App\DTOs\Scan\Database;

class DatabaseRuleResult
{
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_UNSUPPORTED = 'unsupported';

    public function __construct(
        public readonly string $ruleId,
        public readonly string $status,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $severity = null,
        public readonly ?string $evidence = null,
        public readonly ?string $remediation = null,
        public readonly array $metadata = []
    ) {
    }

    public static function pass(string $ruleId, string $title, string $description, array $metadata = []): self
    {
        return new self($ruleId, self::STATUS_PASS, $title, $description, null, null, null, $metadata);
    }

    public static function fail(string $ruleId, string $title, string $description, string $severity, ?string $evidence = null, ?string $remediation = null, array $metadata = []): self
    {
        return new self($ruleId, self::STATUS_FAIL, $title, $description, $severity, $evidence, $remediation, $metadata);
    }

    public static function unsupported(string $ruleId, string $title, string $description, array $metadata = []): self
    {
        return new self($ruleId, self::STATUS_UNSUPPORTED, $title, $description, null, null, null, $metadata);
    }
}
