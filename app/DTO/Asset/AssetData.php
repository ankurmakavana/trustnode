<?php

namespace App\DTO\Asset;

use App\Enums\Asset\AssetCriticality;
use App\Enums\Asset\AssetStatus;
use App\Enums\Asset\AssetType;

readonly class AssetData
{
    /**
     * @param  string[]|null  $tags
     */
    public function __construct(
        public string $name,
        public AssetType $type,
        public string $value,
        public ?string $description = null,
        public AssetCriticality $criticality = AssetCriticality::MEDIUM,
        public AssetStatus $status = AssetStatus::ACTIVE,
        public float $risk_score = 0.00,
        public ?string $owner = null,
        public ?string $notes = null,
        public ?int $asset_group_id = null,
        public ?array $tags = null
    ) {}

    /**
     * Build DTO from request array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: is_string($data['type']) ? AssetType::from($data['type']) : $data['type'],
            value: $data['value'],
            description: $data['description'] ?? null,
            criticality: isset($data['criticality'])
                ? (is_string($data['criticality']) ? AssetCriticality::from($data['criticality']) : $data['criticality'])
                : AssetCriticality::MEDIUM,
            status: isset($data['status'])
                ? (is_string($data['status']) ? AssetStatus::from($data['status']) : $data['status'])
                : AssetStatus::ACTIVE,
            risk_score: isset($data['risk_score']) ? (float) $data['risk_score'] : 0.00,
            owner: $data['owner'] ?? null,
            notes: $data['notes'] ?? null,
            asset_group_id: isset($data['asset_group_id']) ? (int) $data['asset_group_id'] : null,
            tags: $data['tags'] ?? null
        );
    }
}
