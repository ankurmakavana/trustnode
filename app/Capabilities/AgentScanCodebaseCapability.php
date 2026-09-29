<?php

namespace App\Capabilities;

use App\Contracts\AgentCapabilityInterface;
use App\Services\Scan\RepositoryScanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Scan;
use Illuminate\Support\Str;

class AgentScanCodebaseCapability implements AgentCapabilityInterface
{
    protected RepositoryScanner $scanner;
    protected \App\Contracts\AgentQueueInterface $queue;

    public function __construct(RepositoryScanner $scanner, \App\Contracts\AgentQueueInterface $queue)
    {
        $this->scanner = $scanner;
        $this->queue = $queue;
    }

    public function getId(): string
    {
        return 'agent:scan_codebase';
    }

    public function getDescription(): string
    {
        return 'Scans a codebase repository for vulnerabilities and secrets.';
    }

    public function getRequiredPermission(): ?string
    {
        return 'agent:scan_codebase';
    }

    public function getRiskLevel(): string
    {
        return 'MEDIUM';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'target_path' => ['type' => 'string']
            ],
            'required' => ['target_path']
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
        $targetPath = $arguments['target_path'] ?? '';
        
        $canonicalTarget = realpath($targetPath);
        $basePath = realpath(base_path());
        
        if ($canonicalTarget === false || !str_starts_with($canonicalTarget, $basePath)) {
            throw new \RuntimeException("Invalid or unauthorized target path. Target must be within base_path.");
        }

        $startedAt = now();
        $agentId = $context['agent_id'] ?? 'unknown';

        $systemUserId = Schema::hasTable('users') ? \App\Models\User::query()->value('id') : null;

        $scan = null;
        if (Schema::hasTable('scans')) {
            $scan = Scan::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Agent Codebase Scan',
                'description' => 'Autonomous codebase scan by Agent.',
                'type' => \App\Enums\Scan\ScanType::LOCAL,
                'engine' => \App\Enums\Scan\ScanEngine::REPOSITORY_SCANNER,
                'target' => $canonicalTarget,
                'status' => \App\Enums\Scan\ScanStatus::RUNNING,
                'progress' => 50,
                'started_at' => $startedAt,
                'created_by' => $systemUserId,
            ]);
        }

        try {
            $findings = $this->scanner->scan($canonicalTarget, $canonicalTarget);

            $completedAt = now();
            $duration = max(1, $completedAt->diffInSeconds($startedAt));

            if ($scan) {
                $scan->update([
                    'status' => \App\Enums\Scan\ScanStatus::COMPLETED,
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
                    Log::warning("AgentScanCodebaseCapability: Queue full. Cannot enqueue finding.", ['error' => $e->getMessage()]);
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
                    'status' => \App\Enums\Scan\ScanStatus::FAILED,
                    'progress' => 100,
                    'completed_at' => now(),
                    'description' => 'Scan failed: ' . $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }
}
