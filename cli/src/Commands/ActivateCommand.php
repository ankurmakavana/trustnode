<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'activate', description: 'Activate TrustNode Professional License')]
class ActivateCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('license_key', InputArgument::REQUIRED, 'License Key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('license_key');
        
        try {
            $data = $this->client->post('api/license/activate', [
                'license_key' => $key
            ]);
            
            if (!empty($data['success'])) {
                $output->writeln("<info>License activated successfully.</info>");
                return Command::SUCCESS;
            }
            
            $output->writeln("<error>Activation failed.</error>");
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
