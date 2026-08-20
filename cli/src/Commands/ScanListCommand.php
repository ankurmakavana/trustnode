<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'scan:list', description: 'List all scans')]
class ScanListCommand extends Command
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
            $data = $this->client->get('api/scans');
            $scans = $data['data'] ?? [];

            if ($input->getOption('json')) {
                $output->writeln(json_encode($scans, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if (empty($scans)) {
                $output->writeln('No scans found.');
                return Command::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['ID', 'Name', 'Type', 'Status', 'Created At']);
            
            foreach ($scans as $scan) {
                $table->addRow([
                    $scan['id'] ?? '-',
                    $scan['name'] ?? '-',
                    $scan['type'] ?? '-',
                    $scan['status'] ?? '-',
                    $scan['created_at'] ?? '-',
                ]);
            }
            $table->render();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
