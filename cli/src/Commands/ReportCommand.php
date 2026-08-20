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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<info>Report generation requested.</info>");
        try {
            $data = $this->client->post('api/reports');
            $jobId = $data['job_id'] ?? $data['id'] ?? 'unknown';
            $output->writeln("Report is queued asynchronously.");
            $output->writeln("Job ID: $jobId");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
