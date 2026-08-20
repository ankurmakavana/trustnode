<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'fix', description: 'Remediate a finding')]
class FixCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('finding_id', InputArgument::REQUIRED, 'ID of the finding to fix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $findingId = $input->getArgument('finding_id');
        $output->writeln("Inspecting finding ID: $findingId...");
        
        $output->writeln("<comment>Automated remediation is not yet available.</comment>");
        return Command::SUCCESS;
    }
}
