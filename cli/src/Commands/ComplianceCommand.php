<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'compliance', description: 'Show compliance matrix')]
class ComplianceCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("<comment>Compliance module is not available in this installation yet.</comment>");
        return Command::SUCCESS;
    }
}
