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

        if ($actionOrTarget === 'status') {
            $scanId = $input->getArgument('scan_id');
            if (!$scanId) {
                $output->writeln('<error>Please provide a scan ID.</error>');
                return Command::FAILURE;
            }

            try {
                $data = $this->client->get('api/scans/' . $scanId);
                $status = $data['data']['status'] ?? 'unknown';
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
        $payload = [
            'name' => 'CLI Scan ' . date('Y-m-d H:i:s'),
            'type' => 'repository',
            'engine' => 'repositoryscanner',
            'target' => $target,
        ];

        try {
            $data = $this->client->post('api/scans', $payload);
            $scanId = $data['data']['id'] ?? 'unknown';
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
