<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SecurityAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $repoName;
    public string $branch;
    public array $counts;
    public string $reportUuid;
    public string $scanUuid;

    public function __construct(string $repoName, string $branch, array $counts, string $reportUuid, string $scanUuid)
    {
        $this->repoName = $repoName;
        $this->branch = $branch;
        $this->counts = $counts;
        $this->reportUuid = $reportUuid;
        $this->scanUuid = $scanUuid;
    }

    public function build()
    {
        return $this->subject("TrustNode Security Alert — Vulnerabilities Found in {$this->repoName}")
                    ->view('emails.security_alert');
    }
}
