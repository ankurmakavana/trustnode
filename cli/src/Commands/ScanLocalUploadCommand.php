<?php

namespace TrustNode\Cli\Commands;

use Illuminate\Console\Command;
use TrustNode\Cli\Http\TrustNodeClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CURLFile;

use TrustNode\Cli\Config\ConfigManager;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'scan:local-upload', description: 'Upload a local zip archive for scanning')]
class ScanLocalUploadCommand extends \Symfony\Component\Console\Command\Command
{
    protected TrustNodeClient $client;
    protected ConfigManager $configManager;

    public function __construct(TrustNodeClient $client, ConfigManager $configManager)
    {
        parent::__construct();
        $this->client = $client;
        $this->configManager = $configManager;
    }

    protected function configure(): void
    {
        $this->addArgument('archive', InputArgument::REQUIRED, 'Path to zip archive');
        $this->addArgument('target', InputArgument::REQUIRED, 'Original target directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $archivePath = $input->getArgument('archive');
        $target = $input->getArgument('target');

        if (!file_exists($archivePath)) {
            $output->writeln('<error>Archive file not found.</error>');
            return 1;
        }

        $output->writeln('Uploading source...');

        // We must use Guzzle or curl directly since TrustNodeClient is just a wrapper for Guzzle.
        // Wait, TrustNodeClient uses Guzzle. We can use it.
        try {
            $client = new \GuzzleHttp\Client(['base_uri' => rtrim($this->configManager->getUrl(), '/') . '/']);
            $token = $this->configManager->getToken();

            $response = $client->request('POST', 'api/scans/local', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
                'multipart' => [
                    [
                        'name'     => 'archive',
                        'contents' => fopen($archivePath, 'r'),
                        'filename' => basename($archivePath)
                    ],
                    [
                        'name'     => 'target',
                        'contents' => $target
                    ]
                ]
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $output->writeln("\n<info>Scan queued successfully.</info>\n");
            $output->writeln("Scan ID: " . $body['id'] . "\n");
            $output->writeln("Check status:\ntrustnode scan status " . $body['id']);
            return 0;

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 413) {
                $output->writeln("\n<error>Error: Local scan archive exceeds the maximum allowed size.</error>");
                $output->writeln("Maximum allowed: 100 MB");
                $output->writeln("\nTry scanning a smaller directory or exclude unnecessary files.");
            } else {
                $output->writeln('<error>Error uploading archive: ' . $e->getMessage() . '</error>');
            }
            return 1;
        } catch (\Exception $e) {
            $output->writeln('<error>Error uploading archive: ' . $e->getMessage() . '</error>');
            return 1;
        } finally {
            if (file_exists($archivePath)) {
                @unlink($archivePath);
            }
        }
    }
}
