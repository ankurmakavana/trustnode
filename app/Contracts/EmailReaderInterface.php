<?php

namespace App\Contracts;

interface EmailReaderInterface
{
    /**
     * securely read and normalize emails into security signals/findings.
     * Implementations MUST NOT accept credentials directly. Credentials
     * must be resolved securely via an internal vault (e.g., IntegrationCredential).
     *
     * @param string $mailbox
     * @param int $limit
     * @param bool $unreadOnly
     * @return array Array of normalized security signals/findings
     */
    public function readAndNormalize(string $mailbox, int $limit = 50, bool $unreadOnly = true): array;
}
