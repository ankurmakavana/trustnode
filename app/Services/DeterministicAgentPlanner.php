<?php

namespace App\Services;

use App\Contracts\AgentPlannerInterface;
use App\Contracts\AgentCapabilityRegistryInterface;
use Illuminate\Support\Facades\Log;

class DeterministicAgentPlanner implements AgentPlannerInterface
{
    protected AgentCapabilityRegistryInterface $registry;
    protected AgentSecurityBoundary $boundary;

    public function __construct(AgentCapabilityRegistryInterface $registry, AgentSecurityBoundary $boundary)
    {
        $this->registry = $registry;
        $this->boundary = $boundary;
    }

    public function plan(string $objective, int $step, ?string $lastAction = null, mixed $lastResult = null): ?array
    {
        // 1. If step is 2 and last action was codebase scan, we assume it's done.
        if ($step > 1 && $lastAction) {
            Log::info("Objective Complete: " . $objective, ['result' => $lastResult]);
            return null;
        }

        // 2. Intent extraction from objective
        $normalized = strtolower($objective);

        $isScanIntent = str_contains($normalized, 'audit')
            || str_contains($normalized, 'scan')
            || str_contains($normalized, 'vulnerabilit');

        if (!$isScanIntent) {
            Log::warning("AgentPlanner: Unrecognized objective intent.", ['objective' => $objective]);
            return null;
        }

        // 3. Capability Discovery
        $capabilities = $this->registry->getCapabilities();

        foreach ($capabilities as $capability) {
            $desc = strtolower($capability->getDescription());

            // Heuristic matching against capability metadata
            $isNetworkIntent = str_contains($normalized, 'network') || str_contains($normalized, 'infrastructure') || str_contains($normalized, 'domain');

            if ($isNetworkIntent && str_contains($desc, 'scan') && str_contains($desc, 'network')) {
                // If the objective has a target (e.g., scan network trustnode.test), we'd extract it.
                // For MVP, we provide a placeholder or extract from intent string.
                $target = 'example.com';
                if (preg_match('/(?:scan|audit)\s+(?:network|infrastructure)?\s*([a-zA-Z0-9\.\-]+)/i', $normalized, $matches)) {
                    if ($matches[1] !== 'network' && $matches[1] !== 'infrastructure') {
                        $target = $matches[1];
                    }
                }

                return [
                    'capability_id' => $capability->getId(),
                    'arguments' => ['target' => $target]
                ];
            }

            if (!$isNetworkIntent && str_contains($desc, 'scan') && (str_contains($desc, 'codebase') || str_contains($desc, 'repository'))) {

                // Extract expected input schema
                $schema = $capability->getInputSchema();
                $args = [];

                // 4. Target Resolution & Validation
                // If the capability requires a target_path, resolve to current authorized workspace boundary
                if (isset($schema['properties']['target_path'])) {
                    $args['target_path'] = base_path();
                }

                // Verify with security boundary (this would normally authorize before execute,
                // but planner can proactively check it. For deterministic MVP, we just supply base_path())
                // In a real LLM setup, the LLM might hallucinate a path, so we validate it.

                return [
                    'capability_id' => $capability->getId(),
                    'arguments' => $args
                ];
            }
        }

        Log::warning("AgentPlanner: No matching capability found for objective.", ['objective' => $objective]);
        return null;
    }
}
