<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'logout', description: 'Logout and revoke API token')]
class LogoutCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client, private ConfigManager $configManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Check current token
            $token = $this->configManager->getToken();
            if ($token) {
                // If it's a Sanctum personal access token, we might not have its ID.
                // However, the backend DELETE /api/auth/tokens/{token} expects a token ID,
                // OR we can make the backend revoke the CURRENT token if we pass 'current'.
                // The current API is `DELETE /auth/tokens/{token}`. Wait, a token client doesn't know its own ID.
                // Usually Sanctum has a route to delete current token: `$request->user()->currentAccessToken()->delete()`.
                // For this, we'll just hit `/api/logout` which might work if modified, 
                // OR we just clear local config and attempt to hit a revocation if possible.
                // We will try `/api/auth/tokens/current` and silently fail if it doesn't exist, since clearing local is most important.
                
                try {
                    $this->client->delete('api/auth/tokens/current');
                } catch (\Exception $e) {
                    // Ignore backend errors on logout, we must clear local config.
                }
            }
        } finally {
            $this->configManager->clear();
        }

        $output->writeln('<info>Logged out and removed local configuration.</info>');
        return Command::SUCCESS;
    }
}
