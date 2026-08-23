<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Config\ConfigManager;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'repair', description: 'Repair local CLI authentication by recreating tokens')]
class RepairCommand extends Command
{
    public function __construct(private ConfigManager $config)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Attempting to repair TrustNode CLI authentication...\n");

        $envPath = __DIR__ . '/../../../.env';
        if (!file_exists($envPath)) {
            $output->writeln("<error>Could not locate the main .env file at $envPath.</error>");
            $output->writeln("Please run the full installer script again.");
            return Command::FAILURE;
        }

        $envContent = file_get_contents($envPath);
        if (preg_match('/^TRUSTNODE_API_TOKEN=(.*)$/m', $envContent, $matches)) {
            $token = trim($matches[1]);
            $url = $this->config->getUrl() ?? 'http://nginx';
            
            $this->config->save($url, $token);
            $output->writeln("<info>✓ Successfully restored API token from .env to CLI configuration.</info>");
            $output->writeln("Run 'trustnode doctor' to verify.");
            return Command::SUCCESS;
        } else {
            $output->writeln("<error>No TRUSTNODE_API_TOKEN found in the main .env file.</error>");
            $output->writeln("Please run the full installer script again to generate a new token.");
            return Command::FAILURE;
        }
    }
}
