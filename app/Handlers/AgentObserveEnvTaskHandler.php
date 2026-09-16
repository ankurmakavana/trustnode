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
        $cacheKey = 'agent_observe_env_hash_' . md5($target);

        $lastHash = Cache::get($cacheKey);

        // Deterministic state/change mechanism
        if ($lastHash !== $hash) {
            Log::info("AgentObserveEnvTaskHandler: Detected change in target security state.", ['target' => $target]);
            Cache::put($cacheKey, $hash);

            $lines = explode("\n", $content);
            $findings = $this->scanner->scan($content, $lines, basename($target), '');
            
            $agentId = $this->agentService->getAgentId();

            foreach ($findings as $finding) {
                // NormalizedFinding is DTO with public readonly properties. json encode/decode converts it to array.
                $findingArray = json_decode(json_encode($finding), true);
                
                try {
                    $this->queue->enqueue($agentId, 'agent.report_finding', [
                        'finding' => $findingArray
                    ]);
                } catch (\App\Exceptions\QueueFullException $e) {
                    Log::warning("AgentObserveEnvTaskHandler: Queue full. Cannot enqueue finding task.", ['error' => $e->getMessage()]);
                    // Fail safely - stop enqueuing if queue is full.
                    break;
                }
            }
        }
    }
}
