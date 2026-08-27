<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'repositories', description: 'List available repositories')]
class RepositoriesCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = $this->client->get('api/repositories');
            $repos = isset($data['data']) ? $data['data'] : $data;
            if (!is_array($repos)) {
                $repos = [];
            }
            if (empty($repos)) {
                $output->writeln("No repositories found.");
                return Command::SUCCESS;
            }

            $output->writeln("Repositories\n");
            foreach ($repos as $repo) {
                $latestScan = $repo['latest_scan'] ?? $repo['latestScan'] ?? null;
                $scanId = $latestScan ? ($latestScan['id'] ?? 'unknown') : 'none';
                
                if ($latestScan) {
                    $status = $latestScan['status'] ?? 'unknown';
                    $dateStr = $latestScan['completed_at'] ?? $latestScan['created_at'] ?? null;
                    if ($dateStr) {
                        $date = date('d M Y, h:i A', strtotime($dateStr));
                        $scanStatus = sprintf("%s — %s", $status, $date);
                    } else {
                        $scanStatus = $status;
                    }
                } else {
                    $scanStatus = 'no scans';
                }

                $output->writeln(sprintf("- %s", $repo['name'] ?? 'Unknown'));
                $output->writeln(sprintf("  Repository ID: %s", $repo['id'] ?? '?'));
                $output->writeln(sprintf("  Latest Scan ID: %s", $scanId));
                $output->writeln(sprintf("  Latest Scan: %s\n", $scanStatus));
            }
            $output->writeln("Use `trustnode report <Latest Scan ID>` to generate a report.");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
