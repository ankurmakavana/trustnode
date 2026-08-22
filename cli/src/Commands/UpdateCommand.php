<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use GuzzleHttp\Client;
use ZipArchive;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'update', description: 'Update TrustNode to the latest release')]
class UpdateCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $token = getenv('TRUSTNODE_INSTALLATION_TOKEN');
        if (!$token) {
            $output->writeln('<error>Missing TRUSTNODE_INSTALLATION_TOKEN in environment.</error>');
            return Command::FAILURE;
        }

        $platformUrl = 'https://trustnode.in';
        $client = new Client(['base_uri' => $platformUrl]);

        $output->writeln('<info>Checking for updates...</info>');

        try {
            $response = $client->get('/api/v1/releases/latest', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            if (!isset($data['download_url'])) {
                $output->writeln('<error>Failed to parse release metadata.</error>');
                return Command::FAILURE;
            }

            $downloadUrl = $data['download_url'];
            $version = $data['version'];
            
            $output->writeln("<info>Found latest version: {$version}. Downloading...</info>");

            $tempZip = '/tmp/trustnode-release.zip';
            $client->get($downloadUrl, ['sink' => $tempZip]);

            $output->writeln('<info>Extracting artifact...</info>');
            
            $zip = new ZipArchive;
            if ($zip->open($tempZip) === TRUE) {
                // Extract to the application root directory inside the container
                $zip->extractTo(base_path());
                $zip->close();
                unlink($tempZip);
            } else {
                $output->writeln('<error>Failed to extract release zip.</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>Updating dependencies and database...</info>');
            
            // Run composer install and migrations
            shell_exec('composer install --no-interaction --prefer-dist 2>&1');
            shell_exec('php artisan migrate --force 2>&1');
            
            // Run build if needed (assuming node is available or pre-built in zip)
            // For artifact-based, usually vendor and public/build are included.

            $output->writeln("<info>TrustNode updated to version {$version} successfully!</info>");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Update failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
