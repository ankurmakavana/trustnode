<?php

namespace App\Services;

use App\Contracts\DeepSeekHarnessAdapterInterface;
use App\Contracts\DeepSeekHarnessExecutionResultInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;
use JsonException;

class DeepSeekHarnessAdapter implements DeepSeekHarnessAdapterInterface
{
    protected string $workingDir;
    protected int $timeout;

    public function __construct(string $workingDir = null, int $timeout = 30)
    {
        $this->workingDir = $workingDir ?? base_path('scratch/deepseek-harness');
        $this->timeout = $timeout;
    }

    public function execute(
        string $agentId,
        string $executionId,
        string $operation,
        array $arguments,
        array $executionContext = []
    ): DeepSeekHarnessExecutionResultInterface {
        try {
            $payload = json_encode([
                'jsonrpc' => '2.0',
                'id' => $executionId,
                'method' => $operation,
                'params' => [
                    'agent_id' => $agentId,
                    'arguments' => $arguments,
                    'context' => $executionContext
                ]
            ], JSON_THROW_ON_ERROR);

            $process = $this->createProcess([
                'pnpm', 'dsh', '--profile', 'sdk-minimal'
            ], $this->workingDir);
            
            $process->setTimeout($this->timeout);
            $process->setInput($payload . "\n");

            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                return $this->parseResponse($output, $executionId);
            }

            return new DeepSeekHarnessExecutionResult(
                false,
                false,
                $executionId,
                [],
                "Harness process failed: " . $process->getErrorOutput()
            );

        } catch (ProcessTimedOutException $e) {
            return new DeepSeekHarnessExecutionResult(
                false,
                true,
                $executionId,
                [],
                "Execution timed out after {$this->timeout} seconds."
            );
        } catch (Throwable $e) {
            Log::error('DeepSeekHarnessAdapter: Execution error', ['error' => $e->getMessage()]);
            return new DeepSeekHarnessExecutionResult(
                false,
                false,
                $executionId,
                [],
                "Internal adapter error: " . $e->getMessage()
            );
        }
    }

    protected function parseResponse(string $output, string $executionId): DeepSeekHarnessExecutionResultInterface
    {
        $lines = explode("\n", trim($output));
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            try {
                $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                
                if (!isset($data['id']) || (string)$data['id'] !== $executionId) {
                    continue;
                }

                if (isset($data['error'])) {
                    return new DeepSeekHarnessExecutionResult(
                        false,
                        false,
                        $executionId,
                        [],
                        is_array($data['error']) ? json_encode($data['error']) : (string)$data['error']
                    );
                }

                return new DeepSeekHarnessExecutionResult(
                    true,
                    false,
                    $executionId,
                    $data['result'] ?? [],
                    null
                );

            } catch (JsonException $e) {
                continue;
            }
        }

        return new DeepSeekHarnessExecutionResult(
            false,
            false,
            $executionId,
            [],
            "No valid JSON-RPC response found in Harness output."
        );
    }

    protected function createProcess(array $command, string $cwd): Process
    {
        return new Process($command, $cwd);
    }
}
