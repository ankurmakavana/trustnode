<?php

namespace App\Contracts;

interface DeepSeekHarnessExecutionResultInterface
{
    public function isSuccess(): bool;
    public function isCancelled(): bool;
    public function getExecutionId(): string;
    public function getOutput(): array;
    public function getError(): ?string;
}
