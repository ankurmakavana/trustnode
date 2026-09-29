<?php

namespace App\Handlers;

use App\Contracts\AgentTaskHandlerInterface;
use App\Contracts\AgentQueueInterface;
use App\Services\AgentService;
use App\Services\Scan\Scanners\SecretScanner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentObserveEnvTaskHandler implements AgentTaskHandlerInterface
{
    protected SecretScanner $scanner;
    protected AgentQueueInterface $queue;
    protected AgentService $agentService;

    public function __construct(SecretScanner $scanner, AgentQueueInterface $queue, AgentService $agentService)
    {
        $this->scanner = $scanner;
        $this->queue = $queue;
        $this->agentService = $agentService;
    }

    public function supports(string $type): bool
    {
        return $type === 'agent.observe_env';
    }

    public function handle(array $task): void
    {
        $payloadTarget = $task['payload']['target'] ?? null;
        if ($payloadTarget !== null && !is_string($payloadTarget)) {
            Log::warning("AgentObserveEnvTaskHandler: Malformed target payload.");
            return;
        }
        
        $expectedPath = base_path('.env');
        
        $target = $payloadTarget !== null ? (string)$payloadTarget : $expectedPath;
        
        $canonicalTarget = realpath($target);
        
        if ($canonicalTarget === false) {
            Log::info("AgentObserveEnvTaskHandler: Target file does not exist or is unreachable.", ['target' => $target]);
            return;
        }

        $canonicalExpected = realpath($expectedPath);
        
        if ($canonicalExpected === false || $canonicalTarget !== $canonicalExpected) {
            Log::warning("AgentObserveEnvTaskHandler: Invalid or unauthorized target path.", ['target' => $target]);
            return;
        }

        if (!is_readable($canonicalTarget)) {
            Log::info("AgentObserveEnvTaskHandler: Target file is not readable.", ['target' => $canonicalTarget]);
            return;
        }

        $content = file_get_contents($canonicalTarget);
        if ($content === false) {
            return;
        }

        $hash = hash('sha256', $content);
        $cacheKey = 'agent_observe_env_hash_' . md5($canonicalTarget);

        $lastHash = Cache::get($cacheKey);

        // Deterministic state/change mechanism
        if ($lastHash !== $hash) {
            Log::info("AgentObserveEnvTaskHandler: Detected change in target security state.", ['target' => $target]);
            Cache::put($cacheKey, $hash);

            $startedAt = now();
            $agentId = $this->agentService->getAgentId();

            $systemUserId = \Illuminate\Support\Facades\Schema::hasTable('users') ? \App\Models\User::query()->value('id') : null;

            // Create real Scan record tracking execution state if database is available
            $scan = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('scans')) {
                try {
                    $scan = \App\Models\Scan::create([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'name' => 'Agent Security Observation: ' . basename($target),
                        'description' => 'Real-time security scan performed by TrustNode Agent on environment state change.',
                        'type' => \App\Enums\Scan\ScanType::LOCAL,
                        'engine' => \App\Enums\Scan\ScanEngine::REPOSITORY_SCANNER,
                        'target' => basename($target),
                        'status' => \App\Enums\Scan\ScanStatus::RUNNING,
                        'progress' => 50,
                        'started_at' => $startedAt,
                        'created_by' => $systemUserId,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("AgentObserveEnvTaskHandler: Unable to persist scan record.", ['error' => $e->getMessage()]);
                }
            }

            try {
                $lines = explode("\n", $content);
                $findings = $this->scanner->scan($content, $lines, basename($target), '');

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
                        Log::warning("AgentObserveEnvTaskHandler: Queue full. Cannot enqueue finding task.", ['error' => $e->getMessage()]);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $completedAt = now();
                $duration = max(1, $completedAt->diffInSeconds($startedAt));

                if ($scan) {
                    $scan->update([
                        'status' => \App\Enums\Scan\ScanStatus::FAILED,
                        'progress' => 100,
                        'completed_at' => $completedAt,
                        'duration' => $duration,
                        'description' => 'Scan failed: ' . $e->getMessage(),
                    ]);
                }

                Log::error("AgentObserveEnvTaskHandler: Scanner execution failed.", [
                    'scan_id' => $scan ? $scan->id : null,
                    'error' => $e->getMessage()
                ]);

                throw $e;
            }
        }
    }
}
