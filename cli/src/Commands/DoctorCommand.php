<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'doctor', description: 'Diagnose CLI authentication and connectivity')]
class DoctorCommand extends Command
{
    public function __construct(private TrustNodeClient $client, private ConfigManager $config)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("Running TrustNode Diagnostics...\n");

        $token = $this->config->getToken();
        if (!$token) {
            $output->writeln("<error>❌ API Token: Not found.</error>");
            $output->writeln("Please run 'trustnode repair' to fix the missing token.");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ API Token: Found.</info>");

        $url = $this->config->getUrl() ?? 'http://nginx';
        $output->writeln("<info>✓ API URL: $url</info>");

        $output->write("Testing API connection... ");
        try {
            $this->client->get('api/system/status');
            $output->writeln("<info>OK</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>FAILED</error>");
            $output->writeln("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $output->write("Testing authentication... ");
        try {
            $this->client->get('api/users/preferences');
            $output->writeln("<info>OK</info>");
        } catch (\Exception $e) {
            $output->writeln("<error>FAILED</error>");
            $output->writeln("Error: " . $e->getMessage());
            $output->writeln("Your authentication token is invalid or expired. Run 'trustnode repair'.");
            return Command::FAILURE;
        }

        $output->writeln("\n<info>✓ All checks passed! Your CLI is fully configured.</info>");
        return Command::SUCCESS;
    }
}
