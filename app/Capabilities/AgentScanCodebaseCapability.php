<?php

namespace App\Capabilities;

use App\Contracts\AgentCapabilityInterface;
use App\Services\Scan\RepositoryScanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Scan;
use Illuminate\Support\Str;
use App\Models\CodebaseProject;
use App\Enums\CodebaseProject\CodebaseProjectStatus;

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
        return 'Scans a codebase repository or a registered CodebaseProject for vulnerabilities and secrets.';
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
                'target_path' => ['type' => 'string'],
                'project_id' => ['type' => 'integer'],
                'scan_profile' => ['type' => 'string']
            ]
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
        $systemUserId = \App\Services\TenantContext::currentUserId();
        if (!$systemUserId) {
            throw new \RuntimeException("Missing tenant context for codebase scan.");
        }

        $user = \App\Models\User::find($systemUserId);
        $organizationId = $user ? $user->organizations()->first()?->id : null;

        $projectId = $arguments['project_id'] ?? null;
        $targetPath = $arguments['target_path'] ?? null;
        
        $scanProfile = 'standard';
        $canonicalTarget = false;
        $project = null;

        $basePath = realpath(base_path());
        $scanMountDir = realpath(config('agent.scan_mount_dir', '/mnt/trustnode-scan'));
        
        $mountPrefix = $scanMountDir !== false ? rtrim($scanMountDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;
        $basePrefix = $basePath !== false ? rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;

        if ($projectId) {
            $project = CodebaseProject::find($projectId);
            
            if (!$project || $project->status !== CodebaseProjectStatus::ACTIVE || $project->organization_id !== $organizationId) {
                throw new \RuntimeException("Invalid, unauthorized, or inactive codebase project.");
            }
            
            $scanProfile = $project->scan_profile?->value ?? $arguments['scan_profile'] ?? 'standard';
            
            if (!$mountPrefix) {
                throw new \RuntimeException("Scan mount directory is not configured or unavailable.");
            }
            
            $targetPath = rtrim($scanMountDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($project->path, '/\\');
            $canonicalTarget = realpath($targetPath);
            
            if ($canonicalTarget === false || !is_dir($canonicalTarget)) {
                throw new \RuntimeException("Invalid target path: directory does not exist or cannot be resolved.");
            }
            
            // Boundary check avoiding prefix-sibling attacks
            if ($canonicalTarget !== rtrim($mountPrefix, DIRECTORY_SEPARATOR) && !str_starts_with($canonicalTarget, $mountPrefix)) {
                throw new \RuntimeException("Invalid or unauthorized target path. Target must be within the designated scan mount.");
            }
        } elseif ($targetPath) {
            // Legacy flow
            $canonicalTarget = realpath($targetPath);
            if ($canonicalTarget === false || !is_dir($canonicalTarget)) {
                throw new \RuntimeException("Invalid target path: directory does not exist or cannot be resolved.");
            }
            
            $isWithinBasePath = $basePrefix !== null && ($canonicalTarget === rtrim($basePrefix, DIRECTORY_SEPARATOR) || str_starts_with($canonicalTarget, $basePrefix));
            $isWithinScanMount = $mountPrefix !== null && ($canonicalTarget === rtrim($mountPrefix, DIRECTORY_SEPARATOR) || str_starts_with($canonicalTarget, $mountPrefix));
            
            if (!$isWithinBasePath && !$isWithinScanMount) {
                throw new \RuntimeException("Invalid or unauthorized target path. Target must be within base_path or the designated scan mount.");
            }
        } else {
            throw new \RuntimeException("Missing project_id or target_path.");
        }

        $startedAt = now();
        $agentId = $context['agent_id'] ?? 'unknown';

        $scan = null;
        if (Schema::hasTable('scans')) {
            $scanData = [
                'uuid' => (string) Str::uuid(),
                'name' => $project ? 'Project Scan: ' . $project->name : 'Agent Codebase Scan',
                'description' => 'Autonomous codebase scan by Agent.',
                'type' => \App\Enums\Scan\ScanType::LOCAL,
                'engine' => \App\Enums\Scan\ScanEngine::REPOSITORY_SCANNER,
                'target' => $canonicalTarget,
                'status' => \App\Enums\Scan\ScanStatus::RUNNING,
                'progress' => 50,
                'started_at' => $startedAt,
                'created_by' => $systemUserId,
                'organization_id' => $organizationId,
            ];
            
            // If the schema supports codebase_project_id, attach it.
            // (Documented integration point)
            if (Schema::hasColumn('scans', 'codebase_project_id')) {
                $scanData['codebase_project_id'] = $project ? $project->id : null;
            }
            
            $scan = Scan::create($scanData);
        }

        try {
            // Note: $scanProfile could be passed to the scanner if supported.
            // We pass it to the scanner if the underlying method signature updates, 
            // otherwise we stick to existing behaviour.
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
