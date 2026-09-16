<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use App\DTO\Finding\FindingData;
use App\DTOs\Import\NormalizedFinding;
use App\Models\FindingIdentity;
use App\Services\Finding\FindingService;
use App\Services\Import\FingerprintService;
use Illuminate\Support\Facades\Log;

class AgentReportFindingTaskHandler implements AgentTaskHandlerInterface
{
    protected FindingService $findingService;
    protected FingerprintService $fingerprintService;

    public function __construct(FindingService $findingService, FingerprintService $fingerprintService)
    {
        $this->findingService = $findingService;
        $this->fingerprintService = $fingerprintService;
    }

    public function supports(string $type): bool
    {
        return $type === 'agent.report_finding';
    }

    public function handle(array $task): void
    {
        $payload = $task['payload'] ?? [];
        $userId = $payload['created_by'] ?? 1; 
        $assetId = $payload['asset_id'] ?? null;
        
        $normalized = new NormalizedFinding([
            'title' => $payload['title'] ?? 'Agent Security Observation',
            'severity' => $payload['severity'] ?? 'low',
            'scanner' => 'AgentObservation',
            'cve' => $payload['cve'] ?? null
        ]);
        
        $fingerprint = $this->fingerprintService->generate($normalized, $assetId);
        
        $identityHash = hash('sha256', "agent_obs_{$userId}|{$fingerprint}|{$assetId}||");
        
        $identity = FindingIdentity::firstOrCreate(
            ['identity_hash' => $identityHash],
            [
                'fingerprint' => $fingerprint,
                'asset_id' => $assetId,
                'created_by' => $userId,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]
        );
        
        $identity->update(['last_seen_at' => now()]);

        $evidence = $payload['evidence'] ?? null;
        if (is_string($evidence) && mb_strlen($evidence) > 2000) {
            $evidence = mb_substr($evidence, 0, 2000) . '... [truncated]';
        }

        $findingData = new FindingData(
            title: $normalized->title,
            cve: $normalized->cve,
            cvss_score: isset($payload['cvss_score']) ? (float) $payload['cvss_score'] : null,
            severity: strtolower($normalized->severity),
            status: 'open',
            category: $payload['category'] ?? 'Agent Observation',
            cwe: $payload['cwe'] ?? null,
            description: $payload['description'] ?? 'Observation recorded by TrustNode Agent.',
            technical_details: $payload['technical_details'] ?? null,
            business_impact: $payload['business_impact'] ?? null,
            remediation: $payload['remediation'] ?? null,
            evidence: $evidence,
            asset_id: $assetId,
            target_id: $payload['target_id'] ?? null,
            scan_id: null,
            assigned_analyst: null
        );

        $finding = $this->findingService->create($findingData, $userId);
        
        $finding->update([
            'finding_identity_id' => $identity->id, 
            'fingerprint' => $fingerprint, 
            'scanner' => 'AgentObservation'
        ]);

        Log::info('AgentReportFindingTaskHandler: finding created successfully.', [
            'agent_id' => $task['agent_id'] ?? 'unknown',
            'finding_id' => $finding->id,
            'identity_id' => $identity->id
        ]);
    }
}
