<?php

namespace App\Contracts;

interface AgentCapabilityInterface
{
    public function getId(): string;
    public function getDescription(): string;
    public function getRequiredPermission(): ?string;
    public function getRiskLevel(): string;
    public function getInputSchema(): array;
    public function getOutputSchema(): array;
    public function execute(array $arguments, array $context): mixed;
}
