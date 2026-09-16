<?php

namespace App\Contracts;

interface AgentApprovalServiceInterface
{
    public function authorizeRequest(string $agentId, string $operation, string $capability, array $arguments): void;
    
    public function approve(int $approvalId, int $userId, ?int $durationMinutes = null): void;
    public function reject(int $approvalId, int $userId): void;
    public function revoke(int $approvalId, int $userId): void;
}
