<?php

namespace App\Services;

use App\Contracts\AgentApprovalServiceInterface;
use App\Contracts\AgentCapabilityRegistryInterface;
use App\Models\AgentApproval;
use App\Exceptions\AgentSecurityException;
use Illuminate\Support\Facades\Log;

class AgentApprovalService implements AgentApprovalServiceInterface
{
    protected AgentCapabilityRegistryInterface $capabilityRegistry;

    public function __construct(AgentCapabilityRegistryInterface $capabilityRegistry)
    {
        $this->capabilityRegistry = $capabilityRegistry;
    }

    public function authorizeRequest(string $agentId, string $operation, string $capability, array $arguments): void
    {
        $fingerprint = $this->calculateFingerprint($agentId, $operation, $capability, $arguments);

        $approval = AgentApproval::where('agent_id', $agentId)
            ->where('request_fingerprint', $fingerprint)
            ->orderBy('id', 'desc')
            ->first();

        if (!$approval) {
            AgentApproval::create([
                'agent_id' => $agentId,
                'operation' => $operation,
                'capability' => $capability,
                'scope' => $arguments,
                'request_fingerprint' => $fingerprint,
                'status' => 'pending',
            ]);
            
            Log::info('AgentApprovalService: Created new pending approval request.', [
                'agent_id' => $agentId,
                'operation' => $operation,
                'capability' => $capability
            ]);

            throw new AgentSecurityException("Execution denied: Approval pending for operation [{$operation}].");
        }

        if ($approval->status !== 'approved') {
            throw new AgentSecurityException("Execution denied: Approval status is [{$approval->status}].");
        }

        if ($approval->expires_at && $approval->expires_at->isPast()) {
            $approval->update(['status' => 'expired']);
            throw new AgentSecurityException("Execution denied: Approval has expired.");
        }

        // Convert approval into a runtime capability grant
        $this->capabilityRegistry->addGrant(
            $approval->agent_id,
            $approval->capability,
            $approval->scope ?? [],
            $approval->constraints ?? []
        );
    }

    public function approve(int $approvalId, int $userId, ?int $durationMinutes = null): void
    {
        $approval = AgentApproval::findOrFail($approvalId);
        
        if ($approval->status !== 'pending') {
            throw new \InvalidArgumentException("Only pending requests can be approved.");
        }

        $approval->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'expires_at' => $durationMinutes ? now()->addMinutes($durationMinutes) : null,
        ]);
    }

    public function reject(int $approvalId, int $userId): void
    {
        $approval = AgentApproval::findOrFail($approvalId);
        if ($approval->status !== 'pending') {
            throw new \InvalidArgumentException("Only pending requests can be rejected.");
        }
        $approval->update([
            'status' => 'rejected',
            'approved_by' => $userId,
        ]);
    }

    public function revoke(int $approvalId, int $userId): void
    {
        $approval = AgentApproval::findOrFail($approvalId);
        if ($approval->status !== 'approved') {
            throw new \InvalidArgumentException("Only approved requests can be revoked.");
        }
        $approval->update([
            'status' => 'revoked',
            'approved_by' => $userId,
        ]);
    }

    protected function calculateFingerprint(string $agentId, string $operation, string $capability, array $arguments): string
    {
        // Canonical sort of arguments
        ksort($arguments);
        $canonicalArgs = json_encode($arguments);
        return hash('sha256', "{$agentId}:{$operation}:{$capability}:{$canonicalArgs}");
    }
}
