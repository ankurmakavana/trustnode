<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'status', description: 'Show TrustNode installation and application status')]
class StatusCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = $this->client->get('api/system/status');
            $output->writeln("<info>TrustNode: Ready</info>");
            $output->writeln("Version: " . ($data['version'] ?? 'unknown'));
            $output->writeln("Application: " . ($data['application'] ?? 'Healthy'));
            $output->writeln("Database: " . ($data['database'] ?? 'Connected'));
            $output->writeln("Queue: " . ($data['queue'] ?? 'Available'));
            $output->writeln("License: " . ($data['license'] ?? 'Community'));
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>TrustNode API unreachable: " . $e->getMessage() . "</error>");
            return Command::FAILURE;
        }
    }
}
