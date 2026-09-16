<?php

namespace App\Contracts;

interface AgentCapabilityRegistryInterface
{
    public const MODE_READ_ONLY = 'READ_ONLY';

    /**
     * Map an operation to a required capability.
     */
    public function registerOperation(string $operation, string $capability): void;

    /**
     * Get the capability required for an operation.
     */
    public function getRequiredCapability(string $operation): ?string;
    
    /**
     * Set the operating mode for a capability.
     */
    public function setCapabilityMode(string $capability, string $mode): void;

    /**
     * Get the operating mode for a capability.
     */
    public function getCapabilityMode(string $capability): string;

    /**
     * Grant a capability to an agent.
     */
    public function addGrant(string $agentId, string $capability, array $scope = [], array $constraints = []): void;
    
    /**
     * Revoke a capability from an agent.
     */
    public function revokeCapability(string $agentId, string $capability): void;

    /**
     * Check if a capability is explicitly revoked.
     */
    public function isRevoked(string $agentId, string $capability): bool;

    /**
     * Get all active grants for an agent's capability.
     */
    public function getGrants(string $agentId, string $capability): array;
}
