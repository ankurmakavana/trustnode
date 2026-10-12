<?php

namespace App\Services\Mail;

use App\Contracts\EmailReaderInterface;
use App\Models\Integration;
use App\Services\Integration\IntegrationCredentialService;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;
use Illuminate\Support\Str;

class ImapEmailReaderAdapter implements EmailReaderInterface
{
    protected IntegrationCredentialService $credentialService;
    
    // For testability, allow passing a mock client manager
    public ?ClientManager $clientManager = null;

    public function __construct(IntegrationCredentialService $credentialService)
    {
        $this->credentialService = $credentialService;
    }

    public function readAndNormalize(string $mailbox, int $limit = 50, bool $unreadOnly = true): array
    {
        // 1. Resolve secure integration for this mailbox
        $integration = Integration::withoutGlobalScopes()
            ->where('code', 'email')
            ->where('username', $mailbox)
            ->where('status', 'Connected')
            ->first();

        if (!$integration) {
            throw new \RuntimeException("No connected email integration found for mailbox: {$mailbox}");
        }

        // 2. Fetch plaintext password from secure vault securely
        $password = $this->credentialService->retrieve($integration->id, 'password');

        if (empty($password)) {
            throw new \RuntimeException("No valid password credential found for mailbox: {$mailbox}");
        }

        // 3. Connect via IMAP
        $cm = $this->clientManager ?? new ClientManager();
        
        $client = $cm->make([
            'host'          => $integration->host ?? 'localhost',
            'port'          => $integration->port ?? 993,
            'encryption'    => $integration->tls ? 'ssl' : false,
            'validate_cert' => false,
            'username'      => $integration->username,
            'password'      => $password,
            'protocol'      => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        // 4. Query messages
        $query = $folder->query();
        if ($unreadOnly) {
            $query = $query->unseen();
        }
        
        $messages = $query->limit($limit)->get();
        $signals = [];

        foreach ($messages as $message) {
            /** @var Message $message */
            
            $subject = $message->getSubject() ?? 'No Subject';
            $body = $message->getTextBody() ?? $message->getHTMLBody() ?? '';
            $from = $message->getFrom()[0]->mail ?? 'unknown';
            $date = $message->getDate() ? $message->getDate()->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
            
            $attachmentsCount = $message->getAttachments() ? $message->getAttachments()->count() : 0;
            
            // Basic security signal analysis
            $securityTags = $this->analyzeSecuritySignals($subject, $body);
            
            if (!empty($securityTags)) {
                $signals[] = [
                    'id' => $message->getMessageId() ?? (string) Str::uuid(),
                    'sender' => $from,
                    'subject' => $subject,
                    'received_at' => $date,
                    'has_attachments' => $attachmentsCount > 0,
                    'security_signals' => $securityTags,
                    'snippet' => Str::limit($body, 150)
                ];
            }
        }

        $client->disconnect();
        
        return $signals;
    }
    
    private function analyzeSecuritySignals(string $subject, string $body): array
    {
        $tags = [];
        $lowerSubject = strtolower($subject);
        $lowerBody = strtolower($body);
        
        // 1. Credential / Secret exposure
        if (preg_match('/(password|api[_-]?key|secret|token|credential)s?\s*[:=]\s*[^\s]/i', $body)) {
            $tags[] = 'credential_exposure';
        }
        
        // 2. Breach / Security Alert
        if (str_contains($lowerSubject, 'security alert') || 
            str_contains($lowerSubject, 'new login') ||
            str_contains($lowerSubject, 'breach') ||
            str_contains($lowerSubject, 'suspicious activity') ||
            str_contains($lowerSubject, 'action required')) {
            $tags[] = 'security_alert';
        }
        
        // 3. Suspicious Links / Phishing heuristics
        if (str_contains($lowerBody, 'verify your account') || 
            str_contains($lowerBody, 'update your payment') ||
            str_contains($lowerBody, 'urgent') && preg_match('/https?:\/\/[^\s]+/', $lowerBody)) {
            $tags[] = 'suspicious_phishing';
        }
        
        return $tags;
    }
}
