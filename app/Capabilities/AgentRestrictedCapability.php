<?php

namespace App\Capabilities;

use App\Contracts\AgentCapabilityInterface;

class AgentRestrictedCapability implements AgentCapabilityInterface
{
    public function getId(): string
    {
        return 'agent:restricted_action';
    }

    public function getDescription(): string
    {
        return 'A restricted action that requires approval.';
    }

    public function getRequiredPermission(): ?string
    {
        return 'agent:restricted_action';
    }

    public function getRiskLevel(): string
    {
        return 'HIGH';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string']
            ],
            'required' => ['action']
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'success' => ['type' => 'boolean']
            ]
        ];
    }

    public function execute(array $arguments, array $context): mixed
    {
        return ['success' => true, 'action_performed' => $arguments['action'] ?? 'none'];
    }
}
