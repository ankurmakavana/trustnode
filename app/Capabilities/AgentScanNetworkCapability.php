<?php

namespace App\Capabilities;

use App\Contracts\AgentCapabilityInterface;
use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\Services\Scan\Infrastructure\TargetValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Scan;
use Illuminate\Support\Str;

class AgentScanNetworkCapability implements AgentCapabilityInterface
{
    protected NativeInfrastructureScanner $scanner;
    protected TargetValidator $validator;
    protected \App\Contracts\AgentQueueInterface $queue;

    public function __construct(NativeInfrastructureScanner $scanner, TargetValidator $validator, \App\Contracts\AgentQueueInterface $queue)
    {
        $this->scanner = $scanner;
        $this->validator = $validator;
        $this->queue = $queue;
    }

    public function getId(): string
    {
        return 'agent:scan_network';
    }

    public function getDescription(): string
    {
        return 'Scans a network target (domain or IP) for open ports, TLS certificate validity, and HTTP security headers.';
    }

    public function getRequiredPermission(): ?string
    {
        return 'agent:scan_network';
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
                'target' => [
                    'type' => 'string',
                    'description' => 'The domain name or IP address to scan'
                ]
            ],
            'required' => ['target']
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scan_id' => ['type' => 'string'],
                'findings_count' => ['type' => 'integer']
            ]
        ];
    }

    public function execute(array $arguments, array $context): mixed
    {
        $targetString = $arguments['target'] ?? '';
        
        try {
            $validatedTarget = $this->validator->validate($targetString);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException("Invalid network target: " . $e->getMessage());
        }

        $startedAt = now();
        $agentId = $context['agent_id'] ?? 'unknown';

        $systemUserId = Schema::hasTable('users') ? \App\Models\User::query()->value('id') : null;

        $scan = null;
        if (Schema::hasTable('scans')) {
            $scan = Scan::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Agent Network Scan',
                'description' => 'Autonomous network scan by Agent on ' . $validatedTarget->original,
                'type' => \App\Enums\Scan\ScanType::NETWORK_IP,
                'engine' => \App\Enums\Scan\ScanEngine::LOCAL_SCANNER,
                'target' => $validatedTarget->original,
                'status' => \App\Enums\Scan\ScanStatus::RUNNING,
                'progress' => 50,
                'started_at' => $startedAt,
                'created_by' => $systemUserId,
            ]);
        }

        try {
            $findings = $this->scanner->scan($validatedTarget);

            $completedAt = now();
            $duration = max(1, $completedAt->diffInSeconds($startedAt));

            if ($scan) {
                // Update scan status
                $scan->update([
                    'status' => \App\Enums\Scan\ScanStatus::COMPLETED ?? 'completed',
                    'progress' => 100,
                    'completed_at' => $completedAt,
                    'duration' => $duration,
                ]);
            }

            foreach ($findings as $finding) {
                $findingArray = json_decode(json_encode($finding), true);
                try {
                    $this->queue->enqueue($agentId, 'agent.report_finding', [
                        'finding' => $findingArray,
                        'scan_id' => $scan ? $scan->id : null,
                        'agent_id' => $agentId,
                    ]);
                } catch (\App\Exceptions\QueueFullException $e) {
                    Log::warning("AgentScanNetworkCapability: Queue full. Cannot enqueue finding.", ['error' => $e->getMessage()]);
                    break;
                }
            }

            return [
                'scan_id' => $scan ? $scan->id : null,
                'findings_count' => count($findings)
            ];
        } catch (\Throwable $e) {
            if ($scan) {
                $scan->update([
                    'status' => \App\Enums\Scan\ScanStatus::FAILED ?? 'failed',
                    'progress' => 100,
                    'completed_at' => now(),
                    'description' => 'Scan failed: ' . $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
