<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'whoami', description: 'Get current authenticated user information')]
class WhoamiCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $data = $this->client->get('api/auth/me');
            
            if (($data['authenticated'] ?? false) === true) {
                $output->writeln('Authenticated as: <info>' . ($data['data']['email'] ?? 'Unknown') . '</info>');
                $output->writeln('Role: ' . ($data['data']['role']['name'] ?? 'None'));
                $output->writeln('Status: ' . ($data['data']['status'] ?? 'Unknown'));
                return Command::SUCCESS;
            }
            
            $output->writeln('<error>Not authenticated.</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
