<?php

namespace App\Services;

use App\Contracts\AgentSecurityBoundaryInterface;
use App\Exceptions\AgentSecurityException;
use Illuminate\Support\Facades\Log;

class AgentSecurityBoundary implements AgentSecurityBoundaryInterface
{
    protected \App\Contracts\AgentCapabilityRegistryInterface $registry;

    public function __construct(\App\Contracts\AgentCapabilityRegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function authorize(string $agentId, string $operation, array $arguments): void
    {
        $capability = $this->registry->getRequiredCapability($operation);
        
        if (!$capability) {
            Log::warning('AgentSecurityBoundary: Denied unknown operation or no required capability defined.', [
                'agent_id' => $agentId,
                'operation' => $operation
            ]);
            throw new AgentSecurityException("Execution denied: operation [{$operation}] is unknown or has no required capability.");
        }

        $approvalService = app(\App\Contracts\AgentApprovalServiceInterface::class);
        $approvalService->authorizeRequest($agentId, $operation, $capability, $arguments);

        $grants = $this->registry->getGrants($agentId, $capability);
        
        if (empty($grants)) {
            Log::warning('AgentSecurityBoundary: Denied missing capability grant.', [
                'agent_id' => $agentId,
                'operation' => $operation,
                'capability' => $capability
            ]);
            throw new AgentSecurityException("Execution denied: agent [{$agentId}] lacks required capability [{$capability}].");
        }

        $authorized = false;
        foreach ($grants as $grant) {
            if ($this->evaluateScopeAndConstraints($grant['scope'], $grant['constraints'], $arguments)) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            Log::warning('AgentSecurityBoundary: Denied due to scope or constraint mismatch.', [
                'agent_id' => $agentId,
                'operation' => $operation,
                'capability' => $capability
            ]);
            throw new AgentSecurityException("Execution denied: out of scope or constraint mismatch for capability [{$capability}].");
        }
    }

    protected function evaluateScopeAndConstraints(array $scope, array $constraints, array $arguments): bool
    {
        // Require exact matches for all defined scope keys
        foreach ($scope as $key => $value) {
            if (!isset($arguments[$key]) || $arguments[$key] !== $value) {
                return false;
            }
        }

        // We do not implement dynamic constraints in this phase.
        // If constraints are present, fail closed for safety.
        if (!empty($constraints)) {
            return false;
        }

        return true;
    }
}
