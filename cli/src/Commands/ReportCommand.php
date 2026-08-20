<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'report', description: 'Request or check status of a report')]
class ReportCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action_or_scan_id', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Scan ID to generate report, or "status"');
        $this->addArgument('scan_id', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Scan ID (if action is status)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionOrId = $input->getArgument('action_or_scan_id');

        if ($actionOrId === 'status') {
            $scanId = $input->getArgument('scan_id');
            if (!$scanId) {
                $output->writeln('<error>Please provide a scan ID.</error>');
                return Command::FAILURE;
            }

            try {
                $data = $this->client->get("api/scans/$scanId/report/status");
                $status = $data['status'] ?? 'unknown';
                $output->writeln("Scan ID: $scanId");
                $output->writeln("Report Status: $status");
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $scanId = $actionOrId;
        $output->writeln("<info>Report generation requested.</info>");
        try {
            $data = $this->client->post("api/scans/$scanId/report");
            $output->writeln("Report is queued asynchronously.");
            $output->writeln("Scan ID: $scanId");
            $output->writeln("Check progress using: trustnode report status $scanId");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
