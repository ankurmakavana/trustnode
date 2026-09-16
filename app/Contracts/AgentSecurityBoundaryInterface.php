<?php

namespace App\Contracts;

interface AgentSecurityBoundaryInterface
{
    /**
     * Authorize the execution of an operation based on Agent identity and task payload.
     * Must throw AgentSecurityException if the operation is denied.
     *
     * @param string $agentId The trusted runtime agent identity
     * @param string $operation The operation/task type requested
     * @param array $arguments The payload/arguments for the operation
     * @return void
     * @throws \App\Exceptions\AgentSecurityException
     */
    public function authorize(string $agentId, string $operation, array $arguments): void;
}
