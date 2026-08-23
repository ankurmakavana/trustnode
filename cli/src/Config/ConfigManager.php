<?php

namespace TrustNode\Cli\Config;

class ConfigManager
{
    private string $configDir;
    private string $configFile;

    public function __construct()
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
        if (!$homeDir) {
            throw new \RuntimeException('Cannot determine home directory.');
        }

        $this->configDir = $homeDir . '/.trustnode';
        $this->configFile = $this->configDir . '/config';
    }

    public function getUrl(): ?string
    {
        if ($envUrl = getenv('TRUSTNODE_API_URL')) {
            return $envUrl;
        }

        $config = $this->loadConfig();
        return $config['server'] ?? null;
    }

    public function getToken(): ?string
    {
        if ($envToken = getenv('TRUSTNODE_API_TOKEN')) {
            return $envToken;
        }

        $config = $this->loadConfig();
        if (!empty($config['token'])) {
            return $config['token'];
        }

        // Fallback: Read from TrustNode .env file
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/^TRUSTNODE_API_TOKEN=(.*)$/m', $envContent, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    public function save(string $url, string $token): void
    {
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0700, true);
        }

        $config = [
            'server' => $url,
            'token' => $token,
        ];

        file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT));
        chmod($this->configFile, 0600);
    }

    public function clear(): void
    {
        if (file_exists($this->configFile)) {
            unlink($this->configFile);
        }
    }

    private function loadConfig(): array
    {
        if (!file_exists($this->configFile)) {
            return [];
        }

        $content = file_get_contents($this->configFile);
        if (!$content) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }
}
