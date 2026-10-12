<?php

namespace App\Capabilities;

use App\Contracts\AgentCapabilityInterface;
use App\Services\Scan\Infrastructure\TargetValidator;
use App\Contracts\EmailReaderInterface;
use App\Exceptions\AgentSecurityException;
use Illuminate\Support\Str;
use App\Models\Scan;
use Illuminate\Support\Facades\Schema;

class AgentEmailReadCapability implements AgentCapabilityInterface
{
    protected EmailReaderInterface $emailReader;

    public function __construct(EmailReaderInterface $emailReader)
    {
        $this->emailReader = $emailReader;
    }

    public function getId(): string
    {
        return 'agent:email_read';
    }

    public function getName(): string
    {
        return 'Read Security Emails';
    }

    public function getDescription(): string
    {
        return 'Reads and normalizes inbound security-relevant emails (alerts, breach notifications, leaked credentials).';
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
                'mailbox' => [
                    'type' => 'string',
                    'description' => 'The email address/mailbox to read from.'
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max number of emails to read (e.g. 50).'
                ],
                'unread_only' => [
                    'type' => 'boolean',
                    'description' => 'Whether to only read unread emails.'
                ]
            ],
            'required' => ['mailbox']
        ];
    }

    public function getRequiredPermission(): ?string
    {
        return 'agent.email.read';
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scan_id' => ['type' => 'integer'],
                'findings_count' => ['type' => 'integer'],
                'signals' => ['type' => 'array']
            ]
        ];
    }

    public function execute(array $arguments, array $context): mixed
    {
        $mailbox = $arguments['mailbox'] ?? '';
        $limit = $arguments['limit'] ?? 50;
        $unreadOnly = $arguments['unread_only'] ?? true;

        if (empty($mailbox) || !filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid mailbox address: {$mailbox}");
        }

        $agentId = $context['agent_id'] ?? 'unknown';

        $systemUserId = \App\Services\TenantContext::currentUserId();
        if (!$systemUserId) {
            throw new \RuntimeException("Missing tenant context for email scan.");
        }

        $scan = null;
        if (Schema::hasTable('scans')) {
            $scan = Scan::create([
                'uuid' => (string) Str::uuid(),
                'name' => 'Agent Email Security Scan',
                'description' => "Autonomous email scan for mailbox {$mailbox}",
                'type' => \App\Enums\Scan\ScanType::LOCAL ?? 'local',
                'engine' => \App\Enums\Scan\ScanEngine::LOCAL_SCANNER ?? 'localscanner',
                'target' => $mailbox,
                'status' => \App\Enums\Scan\ScanStatus::RUNNING ?? 'running',
                'progress' => 50,
                'started_at' => now(),
                'created_by' => \App\Services\TenantContext::currentUserId(),
            ]);
        }

        try {
            // Note: EmailReaderInterface must internally fetch credentials from the secure vault (IntegrationCredential)
            // for the provided mailbox. The credentials are NEVER passed in $arguments.
            $signals = $this->emailReader->readAndNormalize($mailbox, $limit, $unreadOnly);

            if ($scan) {
                $scan->update([
                    'status' => \App\Enums\Scan\ScanStatus::COMPLETED ?? 'completed',
                    'progress' => 100,
                    'completed_at' => now(),
                    'duration' => 0,
                ]);
            }

            return [
                'scan_id' => $scan ? $scan->id : null,
                'findings_count' => count($signals),
                'signals' => $signals
            ];
        } catch (\Throwable $e) {
            if ($scan) {
                $scan->update([
                    'status' => \App\Enums\Scan\ScanStatus::FAILED ?? 'failed',
                    'progress' => 100,
                    'completed_at' => now(),
                ]);
            }
            throw $e;
        }
    }
}
