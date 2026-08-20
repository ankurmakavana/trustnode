<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'report:status', description: 'Check report status for a scan')]
class ReportStatusCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Scan ID');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');

        try {
            // Note: API for getting report status. We'll use the scan endpoint which 
            // should probably return if a report exists, or we check /api/scans/{id}/report
            $data = $this->client->get("api/scans/{$id}");
            $scan = $data['data'] ?? [];
            
            $status = $scan['report_status'] ?? 'unknown'; // Or whatever field is used in actual API

            if ($input->getOption('json')) {
                $output->writeln(json_encode(['scan_id' => $id, 'report_status' => $status], JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $output->writeln("Report Status for Scan $id: $status");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
