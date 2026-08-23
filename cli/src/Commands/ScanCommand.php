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
                $output->writeln('<comment>Repository authentication is required.</comment>');
                
                $helper = $this->getHelper('question');
                $question = new \Symfony\Component\Console\Question\Question('Enter GitHub Personal Access Token (or press enter to cancel): ');
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
            $output->writeln("Repository: $target");
            $output->writeln("Scan ID: $scanId");
            $output->writeln("Status: queued\n");
            $output->writeln("Check progress:\n");
            $output->writeln("trustnode scan status $scanId");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
