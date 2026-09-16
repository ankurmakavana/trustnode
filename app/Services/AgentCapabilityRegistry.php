<?php

namespace App\Services;

use App\Contracts\AgentCapabilityRegistryInterface;

class AgentCapabilityRegistry implements AgentCapabilityRegistryInterface
{
    protected array $operationMap = [];
    protected array $capabilityModes = [];
    protected array $grants = [];
    protected array $revoked = [];

    public function registerOperation(string $operation, string $capability): void
    {
        $this->operationMap[$operation] = $capability;
    }

    public function getRequiredCapability(string $operation): ?string
    {
        return $this->operationMap[$operation] ?? null;
    }

    public function setCapabilityMode(string $capability, string $mode): void
    {
        $this->capabilityModes[$capability] = $mode;
    }

    public function getCapabilityMode(string $capability): string
    {
        return $this->capabilityModes[$capability] ?? 'UNKNOWN';
    }

    public function addGrant(string $agentId, string $capability, array $scope = [], array $constraints = []): void
    {
        $this->grants[$agentId][$capability][] = [
            'scope' => $scope,
            'constraints' => $constraints,
            'enabled' => true,
        ];
    }

    public function revokeCapability(string $agentId, string $capability): void
    {
        $this->revoked[$agentId][$capability] = true;
        if (isset($this->grants[$agentId][$capability])) {
            foreach ($this->grants[$agentId][$capability] as &$grant) {
                $grant['enabled'] = false;
            }
        }
    }

    public function isRevoked(string $agentId, string $capability): bool
    {
        return $this->revoked[$agentId][$capability] ?? false;
    }

    public function getGrants(string $agentId, string $capability): array
    {
        if (!isset($this->grants[$agentId][$capability])) {
            return [];
        }

        return array_filter($this->grants[$agentId][$capability], function($grant) {
            return $grant['enabled'] === true;
        });
    }
}
