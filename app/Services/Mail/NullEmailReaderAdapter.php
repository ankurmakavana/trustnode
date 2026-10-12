<?php

namespace App\Services\Mail;

use App\Contracts\EmailReaderInterface;

class NullEmailReaderAdapter implements EmailReaderInterface
{
    public function readAndNormalize(string $mailbox, int $limit = 50, bool $unreadOnly = true): array
    {
        // For Phase C: The repository currently lacks an inbound email (IMAP) connector.
        // We STOP at this boundary to avoid bypassing security or hardcoding credentials.
        throw new \BadMethodCallException("Inbound email connector is not implemented. A secure IMAP integration fetching credentials from IntegrationCredential is required.");
    }
}
