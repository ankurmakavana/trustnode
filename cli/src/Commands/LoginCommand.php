<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;
use GuzzleHttp\Client;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'login', description: 'Login to TrustNode using an API token')]
class LoginCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client, private ConfigManager $configManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $urlQuestion = new Question('TrustNode URL [e.g., https://trustnode.example.com]: ');
        $url = $helper->ask($input, $output, $urlQuestion);

        $tokenQuestion = new Question('API token: ');
        $tokenQuestion->setHidden(true);
        $tokenQuestion->setHiddenFallback(false);
        $token = $helper->ask($input, $output, $tokenQuestion);

        if (!$url || !$token) {
            $output->writeln('<error>URL and Token are required.</error>');
            return Command::FAILURE;
        }

        // Temporary client to validate without saving to disk
        $testConfigManager = new ConfigManager();
        $testConfigManager->save($url, $token); // In-memory conceptually, but we write to disk so the client picks it up, wait, better to just mock or use a fresh client.
        
        $testClient = new Client([
            'base_uri' => rtrim($url, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
            'verify' => true,
        ]);
        
        try {
            $response = $testClient->request('GET', 'api/auth/me');
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (($data['authenticated'] ?? false) === true) {
                $this->configManager->save($url, $token);
                $output->writeln('<info>Authenticated successfully.</info>');
                $output->writeln('Server: ' . $url);
                $output->writeln('User: ' . ($data['data']['email'] ?? 'Unknown'));
                return Command::SUCCESS;
            }
            
            $output->writeln('<error>Authentication failed. Token is invalid.</error>');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Authentication failed. Could not connect or invalid token.</error>');
            return Command::FAILURE;
        }
    }
}
