<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

/**
 * Registers a project. The server checks that the path resolves inside the approved scan mount.
 */
#[AsCommand(name: 'project:add', description: 'Register a project inside the scan mount, so it can be scanned')]
class ProjectAddCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Project name');
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path relative to the scan mount');
        $this->addOption('profile', null, InputOption::VALUE_REQUIRED, 'Scan profile (standard or full)', 'standard');
        $this->addOption('continuous', null, InputOption::VALUE_NONE, 'Enable continuous scanning for this project');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getOption('name');
        $path = (string) $input->getOption('path');

        if ($name === '' || $path === '') {
            $output->writeln('<error>--name and --path are required.</error>');

            return Command::FAILURE;
        }

        try {
            $data = $this->client->post('api/projects', [
                'name' => $name,
                'path' => $path,
                'scan_profile' => (string) $input->getOption('profile'),
                'continuous_scan_enabled' => (bool) $input->getOption('continuous'),
            ]);
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Project registered.</info>');
        $output->writeln('Project ID: ' . ($data['data']['id'] ?? 'unknown'));

        return Command::SUCCESS;
    }
}
