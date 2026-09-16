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
        $input = new \Symfony\Component\Process\InputStream();
        $process = $this->createProcess([
            'pnpm', '--silent', 'dsh', '--profile', 'sdk-minimal',
            '--patch', resource_path('deepseek-harness-security.yml')
        ], $this->workingDir);

        $process->setTimeout($this->timeout);
        $process->setInput($input);

        try {
            $process->start();

            $input->write(json_encode([
                'jsonrpc' => '2.0',
                'id' => "init_{$executionId}",
                'method' => 'initialize',
                'params' => [
                    'cwd' => $this->workingDir,
                    'provider' => $executionContext['provider'] ?? 'default',
                    'model' => $executionContext['model'] ?? 'default'
                ]
            ], JSON_THROW_ON_ERROR) . "\n");

            $buffer = '';
            $state = 'wait_init';
            $assistantMessages = [];
            $finalError = null;

            while ($process->isRunning() && $state !== 'done') {
                $process->checkTimeout();
                
                $out = $process->getIncrementalOutput();
                if ($out !== '') {
                    $buffer .= $out;
                    while (($pos = strpos($buffer, "\n")) !== false) {
                        $line = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 1);

                        $line = trim($line);
                        if ($line === '') continue;

                        try {
                            $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                        } catch (JsonException $e) {
                            $finalError = "Malformed JSON-RPC response";
                            $state = 'done';
                            break;
                        }

                        if (isset($data['id'])) {
                            if ($data['id'] === "init_{$executionId}") {
                                if (isset($data['error'])) {
                                    $finalError = is_array($data['error']) ? json_encode($data['error']) : (string)$data['error'];
                                    $state = 'done';
                                    break;
                                }
                                $input->write(json_encode([
                                    'jsonrpc' => '2.0',
                                    'id' => "prompt_{$executionId}",
                                    'method' => 'session/prompt',
                                    'params' => [
                                        'sessionId' => $executionId,
                                        'contentBlocks' => [
                                            [
                                                'type' => 'text',
                                                'text' => json_encode([
                                                    'operation' => $operation,
                                                    'arguments' => $arguments,
                                                    'context' => $executionContext
                                                ])
                                            ]
                                        ]
                                    ]
                                ], JSON_THROW_ON_ERROR) . "\n");
                                $state = 'wait_prompt_receipt';
                            } elseif ($data['id'] === "prompt_{$executionId}") {
                                if (isset($data['error'])) {
                                    $finalError = is_array($data['error']) ? json_encode($data['error']) : (string)$data['error'];
                                    $state = 'done';
                                    break;
                                }
                                $state = 'wait_idle';
                            }
                        } elseif (isset($data['method'])) {
                            if ($data['method'] === 'session.event' && isset($data['params']['event'])) {
                                $event = $data['params']['event'];
                                if (($event['type'] ?? '') === 'assistant/message') {
                                    $assistantMessages[] = $event['data']['message'] ?? [];
                                }
                            } elseif ($data['method'] === 'session.status') {
                                if (($data['params']['sessionId'] ?? '') === $executionId && ($data['params']['status'] ?? '') === 'idle') {
                                    $state = 'done';
                                    break;
                                }
                            }
                        }
                    }
                }

                if ($state !== 'done') {
                    usleep(10000);
                }
            }

            $input->write(json_encode([
                'jsonrpc' => '2.0',
                'id' => "shutdown_{$executionId}",
                'method' => 'shutdown'
            ]) . "\n");
            $input->close();
            $process->stop(1);

            if ($state !== 'done') {
                $finalError = $finalError ?? "Harness process terminated prematurely in state: {$state}";
            }

            if ($finalError) {
                return new DeepSeekHarnessExecutionResult(false, false, $executionId, [], $finalError);
            }

            return new DeepSeekHarnessExecutionResult(true, false, $executionId, $assistantMessages, null);

        } catch (ProcessTimedOutException $e) {
            if (isset($input)) $input->close();
            if (isset($process)) $process->stop(0);
            return new DeepSeekHarnessExecutionResult(false, true, $executionId, [], "Execution timed out after {$this->timeout} seconds.");
        } catch (\Throwable $e) {
            if (isset($input)) $input->close();
            if (isset($process) && $process->isRunning()) $process->stop(0);
            Log::error('DeepSeekHarnessAdapter: Execution error', ['error' => $e->getMessage()]);
            return new DeepSeekHarnessExecutionResult(false, false, $executionId, [], "Internal adapter error: " . $e->getMessage());
        }
    }

    protected function createProcess(array $command, string $cwd): Process
    {
        return new Process($command, $cwd);
    }
}
