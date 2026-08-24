<?php

namespace App\Services\License;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LicenseApiClient
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('license.url', 'https://license.trustnode.io'), '/');
        $this->timeout = config('license.timeout', 5);
    }

    protected function getClient(string $token = null)
    {
        $client = Http::timeout($this->timeout)->acceptJson();
        if ($token) {
            $client->withToken($token);
        }
        return $client;
    }

    public function activate(string $licenseKey, string $installationId, string $fingerprint, ?string $name = null, ?string $hostname = null): array
    {
        try {
            $response = $this->getClient()->post("{$this->baseUrl}/api/v1/licenses/activate", [
                'license_key' => $licenseKey,
                'installation_id' => $installationId,
                'installation_fingerprint' => $fingerprint,
                'installation_name' => $name,
                'hostname' => $hostname,
            ]);

            return $this->parseResponse($response);
        } catch (Exception $e) {
            return $this->handleException($e, 'activate');
        }
    }

    public function validate(string $token): array
    {
        try {
            $response = $this->getClient($token)->post("{$this->baseUrl}/api/v1/licenses/validate");
            return $this->parseResponse($response);
        } catch (Exception $e) {
            return $this->handleException($e, 'validate');
        }
    }

    public function current(string $token): array
    {
        try {
            $response = $this->getClient($token)->get("{$this->baseUrl}/api/v1/licenses/current");
            return $this->parseResponse($response);
        } catch (Exception $e) {
            return $this->handleException($e, 'current');
        }
    }

    public function getLatestRelease(string $token): array
    {
        try {
            // Note: the remote platform endpoint is /api/v1/releases/latest
            $response = $this->getClient($token)->get("{$this->baseUrl}/api/v1/releases/latest");
            
            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $response->json(), // Returns the entire JSON body instead of just 'data' key, depending on API structure
                ];
            }
            
            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json('error') ?? ['code' => 'UNKNOWN_ERROR', 'message' => 'An unknown error occurred.'],
            ];
        } catch (Exception $e) {
            return $this->handleException($e, 'latest_release');
        }
    }

    public function deactivate(string $token): array
    {
        try {
            $response = $this->getClient($token)->post("{$this->baseUrl}/api/v1/licenses/deactivate");
            return $this->parseResponse($response);
        } catch (Exception $e) {
            return $this->handleException($e, 'deactivate');
        }
    }

    protected function parseResponse($response): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'status' => $response->status(),
                'data' => $response->json('data'),
            ];
        }

        return [
            'success' => false,
            'status' => $response->status(),
            'error' => $response->json('error') ?? ['code' => 'UNKNOWN_ERROR', 'message' => 'An unknown error occurred.'],
        ];
    }

    protected function handleException(Exception $e, string $action): array
    {
        Log::warning("License API network failure during {$action}", ['exception' => $e->getMessage()]);
        return [
            'success' => false,
            'status' => 0,
            'error' => ['code' => 'NETWORK_ERROR', 'message' => 'License server is unreachable.'],
        ];
    }
}
