<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

/**
 * Queues a scan for one registered project. Accepts only a project ID, never a path.
 */
#[AsCommand(name: 'project:scan', description: 'Scan a registered project')]
class ProjectScanCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Project ID from project:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (int) $input->getArgument('id');

        if ($id <= 0) {
            $output->writeln('<error>Project ID must be a positive integer.</error>');

            return Command::FAILURE;
        }

        try {
            $data = $this->client->post("api/projects/{$id}/scan");
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>' . ($data['message'] ?? 'Scan queued.') . '</info>');

        return Command::SUCCESS;
    }
}
