<?php

namespace App\Contracts;

interface AgentApprovalServiceInterface
{
    public function authorizeRequest(string $agentId, string $operation, string $capability, array $arguments): void;
    
    public function approve(int $approvalId, ?int $durationMinutes = null): void;
    public function reject(int $approvalId): void;
    public function revoke(int $approvalId): void;
}
