<?php

namespace App\DTO\Target;

use App\Enums\Target\TargetCriticality;
use App\Enums\Target\TargetEnvironment;
use App\Enums\Target\TargetStatus;
use App\Enums\Target\TargetType;

readonly class TargetData
{
    /**
     * @param  string[]|null  $tags
     */
    public function __construct(
        public string $name,
        public TargetType $type,
        public string $value,
        public TargetEnvironment $environment = TargetEnvironment::PRODUCTION,
        public ?string $description = null,
        public TargetCriticality $criticality = TargetCriticality::MEDIUM,
        public TargetStatus $status = TargetStatus::ACTIVE,
        public ?string $scope_notes = null,
        public ?array $tags = null
    ) {}

    /**
     * Build DTO from request array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: is_string($data['type']) ? TargetType::from($data['type']) : $data['type'],
            value: $data['value'],
            environment: isset($data['environment'])
                ? (is_string($data['environment']) ? TargetEnvironment::from($data['environment']) : $data['environment'])
                : TargetEnvironment::PRODUCTION,
            description: $data['description'] ?? null,
            criticality: isset($data['criticality'])
                ? (is_string($data['criticality']) ? TargetCriticality::from($data['criticality']) : $data['criticality'])
                : TargetCriticality::MEDIUM,
            status: isset($data['status'])
                ? (is_string($data['status']) ? TargetStatus::from($data['status']) : $data['status'])
                : TargetStatus::ACTIVE,
            scope_notes: $data['scope_notes'] ?? null,
            tags: $data['tags'] ?? null
        );
    }
}
