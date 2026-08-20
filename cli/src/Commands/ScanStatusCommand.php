<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'scan:status', description: 'Get scan status')]
class ScanStatusCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Scan ID');
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
        $this->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch status changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $isJson = $input->getOption('json');
        $watch = $input->getOption('watch');

        do {
            try {
                $data = $this->client->get("api/scans/{$id}");
                $scan = $data['data'] ?? [];
                
                if ($isJson) {
                    $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
                    if ($watch) return Command::SUCCESS; // Watch not supported for JSON easily without stream
                } else {
                    if ($watch) {
                        $output->write("\033\143"); // clear screen
                    }
                    
                    $output->writeln("ID: " . ($scan['id'] ?? '-'));
                    $output->writeln("Type: " . ($scan['type'] ?? '-'));
                    $output->writeln("Status: " . ($scan['status'] ?? '-'));
                    $output->writeln("Created At: " . ($scan['created_at'] ?? '-'));
                    $output->writeln("Started At: " . ($scan['started_at'] ?? '-'));
                    $output->writeln("Completed At: " . ($scan['completed_at'] ?? '-'));
                    $output->writeln("Finding Count: " . ($scan['finding_count'] ?? 0));
                }

                if ($watch) {
                    $status = $scan['status'] ?? '';
                    if (in_array($status, ['completed', 'failed', 'cancelled'])) {
                        break;
                    }
                    sleep(2); // Polling interval
                }
            } catch (\Exception $e) {
                if ($isJson) {
                    $output->writeln(json_encode(['error' => $e->getMessage()]));
                } else {
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                }
                return Command::FAILURE;
            }
        } while ($watch);

        return Command::SUCCESS;
    }
}
