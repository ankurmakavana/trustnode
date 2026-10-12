<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrustNode\Cli\Http\TrustNodeClient;

/**
 * Lists registered (authorized) projects. Project scans start only from these records.
 */
#[AsCommand(name: 'project:list', description: 'List registered (authorized) projects')]
class ProjectListCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = $this->client->get('api/projects');
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($input->getOption('json')) {
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $projects = $data['data'] ?? [];
        if ($projects === []) {
            $output->writeln('No registered projects. Register one with `project:add`.');

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->table(
            ['ID', 'Name', 'Path', 'Profile', 'Status'],
            array_map(
                fn ($p) => [$p['id'] ?? '', $p['name'] ?? '', $p['path'] ?? '', $p['scan_profile'] ?? '', $p['status'] ?? ''],
                $projects
            )
        );

        return Command::SUCCESS;
    }
}
