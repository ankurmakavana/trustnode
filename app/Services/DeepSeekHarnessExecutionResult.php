<?php

namespace App\Services;

use App\Contracts\DeepSeekHarnessExecutionResultInterface;

class DeepSeekHarnessExecutionResult implements DeepSeekHarnessExecutionResultInterface
{
    protected bool $success;
    protected bool $cancelled;
    protected string $executionId;
    protected array $output;
    protected ?string $error;

    public function __construct(bool $success, bool $cancelled, string $executionId, array $output, ?string $error = null)
    {
        $this->success = $success;
        $this->cancelled = $cancelled;
        $this->executionId = $executionId;
        $this->output = $output;
        $this->error = $error;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getOutput(): array
    {
        return $this->output;
    }

    public function getError(): ?string
    {
        return $this->error;
    }
}
