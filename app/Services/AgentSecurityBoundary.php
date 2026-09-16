<?php

namespace App\Services;

use App\Contracts\AgentSecurityBoundaryInterface;
use App\Exceptions\AgentSecurityException;
use Illuminate\Support\Facades\Log;

class AgentSecurityBoundary implements AgentSecurityBoundaryInterface
{
    public function authorize(string $agentId, string $operation, array $arguments): void
    {
        // TASK 16: Fail Closed Boundary.
        // Since TASK 17 will implement the actual capability model and there are 
        // currently NO production handlers, we deny everything by default.
        // This establishes the strict security perimeter.
        
        Log::warning('AgentSecurityBoundary: Execution denied. Capability engine not yet implemented.', [
            'agent_id' => $agentId,
            'operation' => $operation,
            // Do not log full arguments to prevent secret leakage
        ]);

        throw new AgentSecurityException("Execution denied: operation [{$operation}] is not authorized.");
    }
}
