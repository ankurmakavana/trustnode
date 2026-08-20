<?php

namespace TrustNode\Cli\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use TrustNode\Cli\Config\ConfigManager;

class TrustNodeClient
{
    private ?Client $client = null;

    public function __construct(private ConfigManager $configManager)
    {
    }

    private function getClient(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        $url = $this->configManager->getUrl();
        if (!$url) {
            throw new \RuntimeException('TrustNode server URL is not configured. Please run `trustnode login`.');
        }

        // Enforce HTTPS unless explicitly local
        if (!str_starts_with($url, 'https://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1') && !str_contains($url, 'host.docker.internal') && !str_contains($url, 'nginx')) {
            throw new \RuntimeException('Insecure HTTP connection is not permitted for remote servers. Use HTTPS.');
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $token = $this->configManager->getToken();
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $this->client = new Client([
            'base_uri' => rtrim($url, '/') . '/',
            'headers' => $headers,
            'timeout' => 30.0,
            'verify' => true,
        ]);

        return $this->client;
    }

    public function setClientForTesting(Client $client): void
    {
        $this->client = $client;
    }

    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }
    
    public function download(string $endpoint, string $outputPath): void
    {
        try {
            $this->getClient()->request('GET', $endpoint, [
                'sink' => $outputPath,
            ]);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    private function request(string $method, string $endpoint, array $options = []): array
    {
        try {
            $response = $this->getClient()->request($method, $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    private function handleException(\Exception $e): never
    {
        if ($e instanceof ClientException || $e instanceof ServerException) {
            $response = $e->getResponse();
            $statusCode = $response?->getStatusCode();
            
            $message = match ($statusCode) {
                401 => 'Authentication required or invalid token.',
                403 => 'Insufficient permissions to perform this action.',
                404 => 'Resource not found.',
                422 => 'Validation failed.',
                429 => 'Rate limit exceeded. Please try again later.',
                500 => 'Internal server error.',
                502, 503, 504 => 'Server is temporarily unavailable.',
                default => 'API Error: ' . $statusCode,
            };

            throw new \RuntimeException($message);
        }

        throw new \RuntimeException('Network or unexpected error: ' . $e->getMessage());
    }
}
