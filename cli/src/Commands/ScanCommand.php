<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'scan', description: 'Start a scan or check scan status')]
class ScanCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action_or_target', InputArgument::REQUIRED, 'Repository URL to scan, or "status"');
        $this->addArgument('scan_id', InputArgument::OPTIONAL, 'Scan ID (if action is status)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionOrTarget = $input->getArgument('action_or_target');

        // 1. Verify TrustNode API Authentication
        try {
            $this->client->get('api/users/preferences');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Authentication required') || str_contains($e->getMessage(), 'invalid token')) {
                $output->writeln('<error>TrustNode API Authentication Failed. Please run \'trustnode doctor\' or \'trustnode repair\' to fix your local CLI authentication.</error>');
                return Command::FAILURE;
            }
            $output->writeln('<error>Failed to connect to TrustNode API: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        if ($actionOrTarget === 'status') {
            $scanId = $input->getArgument('scan_id');
            if (!$scanId) {
                $output->writeln('<error>Please provide a scan ID.</error>');
                return Command::FAILURE;
            }

            try {
                $data = $this->client->get('api/scans/' . $scanId);
                $status = $data['data']['status'] ?? ($data['status'] ?? 'unknown');
                $output->writeln("Scan ID: $scanId");
                $output->writeln("Status: $status");
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        // Otherwise it's a repository target
        $target = $actionOrTarget;
        $output->writeln("Checking repository access...");

        $token = null;
        $visibility = 'public';

        try {
            $validation = $this->client->post('api/repositories/validate-access', [
                'repository_url' => $target
            ]);

            if (empty($validation['valid'])) {
                $output->writeln('<comment>Repository access requires authentication.</comment>');
                $output->writeln('<comment>The repository may be private, unavailable, or GitHub rejected anonymous access.</comment>');
                $output->writeln('');
                $output->writeln('<comment>GitHub Personal Access Token is required.</comment>');
                
                $helper = $this->getHelper('question');
                $confirmQuestion = new \Symfony\Component\Console\Question\ConfirmationQuestion('Enter token now? [yes/no] ', false);
                
                if (!$helper->ask($input, $output, $confirmQuestion)) {
                    $output->writeln('<error>Authentication cancelled.</error>');
                    return Command::FAILURE;
                }
                
                $question = new \Symfony\Component\Console\Question\Question('Token: ');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                
                $token = $helper->ask($input, $output, $question);
                
                if (!$token) {
                    $output->writeln('<error>Authentication cancelled. Cannot scan a private repository without credentials.</error>');
                    return Command::FAILURE;
                }
                
                $visibility = 'private';
                
                // Validate again with the token
                $validation = $this->client->post('api/repositories/validate-access', [
                    'repository_url' => $target,
                    'token' => $token
                ]);
                
                if (empty($validation['valid'])) {
                    $output->writeln('<error>The provided token is invalid or does not have access to the repository.</error>');
                    return Command::FAILURE;
                }
            }
            
            $output->writeln("Connecting repository to TrustNode...");
            $repoPayload = [
                'repository_url' => $target,
                'visibility' => $visibility,
            ];
            if ($token) {
                $repoPayload['token'] = $token;
            }
            
            $repoData = $this->client->post('api/repositories', $repoPayload);
            $repoId = $repoData['id'] ?? null;
            
            if (!$repoId) {
                $output->writeln('<error>Failed to connect repository.</error>');
                return Command::FAILURE;
            }
            
            $output->writeln("Triggering scan...");
            $scanData = $this->client->post("api/repositories/{$repoId}/scan");
            $scanId = $scanData['id'] ?? 'unknown';
            
            $output->writeln("<info>Scan started.</info>\n");
            $output->writeln("Scanning repository...\n");
            
            $status = 'queued';
            $progress = 0;
            $filesScanned = 0;
            $scanResource = [];
            
            $section = null;
            if (method_exists($output, 'section')) {
                $section = $output->section();
            }
            
            $lastProgress = -1;
            while (in_array($status, ['queued', 'running', 'generating'])) {
                try {
                    $data = $this->client->get('api/scans/' . $scanId);
                    $scanResource = $data['data'] ?? $data;
                    $status = $scanResource['status'] ?? 'unknown';
                    $progress = $scanResource['progress'] ?? 0;
                    $filesScanned = $scanResource['files_scanned'] ?? 0;
                    
                    $repoName = $target;
                    if (isset($scanResource['repository']['name'])) {
                        $repoName = $scanResource['repository']['name'];
                    }

                    if ($progress !== $lastProgress || $progress == 100) {
                        $stage = 'Queued';
                        if ($progress >= 10) $stage = 'Preparing workspace';
                        if ($progress >= 20) $stage = 'Cloning repository';
                        if ($progress >= 50) $stage = 'Scanning repository';
                        if ($progress >= 80) $stage = 'Processing findings';
                        if ($progress >= 95) $stage = 'Generating report';
                        if ($progress == 100) $stage = 'Completed';
                        
                        $barLength = 20;
                        $filled = (int) round(($progress / 100) * $barLength);
                        $empty = $barLength - $filled;
                        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
                        
                        $out = "[$bar] $progress%\n$stage\n\n";
                        $out .= "Repository     $repoName\n";
                        if ($filesScanned > 0) {
                            $out .= "Files scanned  " . number_format($filesScanned) . "\n";
                        }
                        
                        if ($section) {
                            $section->overwrite($out);
                        } else {
                            // Clear previous lines if standard output, or just print
                            $output->writeln("[$bar] $progress% - $stage");
                        }
                        
                        $lastProgress = $progress;
                    }

                    
                    if (in_array($status, ['completed', 'failed', 'cancelled'])) {
                        break;
                    }
                    
                    usleep(500000); // 0.5s polling for faster response without hammering

                } catch (\Exception $e) {
                    if ($section) {
                        $section->overwrite("<error>Failed to poll status: " . $e->getMessage() . "</error>");
                    } else {
                        $output->writeln("<error>Failed to poll status: " . $e->getMessage() . "</error>");
                    }
                    sleep(5);
                }
            }
            
            if ($status === 'completed') {
                if ($section) {
                    $section->clear();
                }
                $output->writeln("✓ Scan completed.\n");
                $repoName = $target;
                if (isset($scanResource['repository']['name'])) {
                    $repoName = $scanResource['repository']['name'];
                }

                $output->writeln("Repository     $repoName");
                if ($filesScanned > 0) {
                    $output->writeln("Files scanned  " . number_format($filesScanned));
                }
                $output->writeln("");
                
                $findingsCount = $scanResource['findings_count'] ?? 0;
                $severities = $scanResource['severity_counts'] ?? [];
                
                $output->writeln("Findings       " . number_format($findingsCount));
                
                if ($findingsCount > 0) {
                    if (!empty($severities['critical'])) {
                        $output->writeln("Critical       " . number_format($severities['critical']));
                    }
                    if (!empty($severities['high'])) {
                        $output->writeln("High           " . number_format($severities['high']));
                    }
                    if (!empty($severities['medium'])) {
                        $output->writeln("Medium         " . number_format($severities['medium']));
                    }
                    if (!empty($severities['low'])) {
                        $output->writeln("Low            " . number_format($severities['low']));
                    }
                    if (!empty($severities['info'])) {
                        $output->writeln("Info           " . number_format($severities['info']));
                    }
                }
                $output->writeln("");
                if ($findingsCount > 0) {
                    $output->writeln("Evidence stored securely in your TrustNode installation.");
                    $output->writeln("");
                }
                $output->writeln("Next:");
                $output->writeln("  trustnode findings --scan=$scanId");
                $output->writeln("  trustnode report $scanId");
            } elseif ($status === 'failed') {
                if ($section) {
                    $section->clear();
                }
                $output->writeln("✗ Scan failed\n");
                $output->writeln("Scan ID: $scanId\n");
                $output->writeln("Reason:\nScan failed during execution.");
                return Command::FAILURE;
            }

            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
