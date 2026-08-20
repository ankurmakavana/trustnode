<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'findings:list', description: 'List all findings')]
class FindingsListCommand extends Command
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
            $data = $this->client->get('api/findings');
            $findings = $data['data'] ?? [];

            if ($input->getOption('json')) {
                $output->writeln(json_encode($findings, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if (empty($findings)) {
                $output->writeln('No findings found.');
                return Command::SUCCESS;
            }

            $table = new Table($output);
            $table->setHeaders(['ID', 'Title', 'Severity', 'Scan ID']);
            
            foreach ($findings as $finding) {
                $table->addRow([
                    $finding['id'] ?? '-',
                    $finding['title'] ?? '-',
                    $finding['severity'] ?? '-',
                    $finding['scan_id'] ?? '-',
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
