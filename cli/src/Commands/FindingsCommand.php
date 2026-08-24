<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'findings', description: 'List real findings')]
class FindingsCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('severity', null, InputOption::VALUE_REQUIRED, 'Filter by severity (e.g. critical, high)');
        $this->addOption('repository', null, InputOption::VALUE_REQUIRED, 'Filter by repository name');
        $this->addOption('scan', null, InputOption::VALUE_REQUIRED, 'Filter by scan ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $query = [];
            if ($severity = $input->getOption('severity')) {
                $query['severity'] = $severity;
            }
            if ($repository = $input->getOption('repository')) {
                $query['repository'] = $repository;
            }
            if ($scan = $input->getOption('scan')) {
                $query['scan_id'] = $scan;
            }
            
            $qs = empty($query) ? '' : '?' . http_build_query($query);
            $data = $this->client->get('api/findings' . $qs);
            
            $findings = isset($data['data']) ? $data['data'] : $data;
            if (!is_array($findings)) {
                $findings = [];
            }
            if (empty($findings)) {
                $output->writeln("No findings found.");
                return Command::SUCCESS;
            }

            foreach ($findings as $finding) {
                $output->writeln(sprintf("[%s] %s (ID: %s)", strtoupper($finding['severity'] ?? 'UNKNOWN'), $finding['title'] ?? 'Unknown', $finding['id'] ?? '?'));
            }
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
