<?php

namespace TrustNode\Cli\Config;

class ConfigManager
{
    private string $configDir;
    private string $configFile;

    public function __construct(?string $homeDir = null)
    {
        $home = $homeDir ?? getenv('HOME') ?: getenv('USERPROFILE');
        if (!$home) {
            throw new \RuntimeException('Cannot determine home directory.');
        }

        $this->configDir = rtrim($home, '/\\') . '/.trustnode';
        $this->configFile = $this->configDir . '/config';
    }

    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    public function getPidFile(): string
    {
        return $this->configDir . '/agent.pid';
    }

    public function getUrl(): ?string
    {
        if ($envUrl = getenv('TRUSTNODE_API_URL')) {
            return $envUrl;
        }

        $config = $this->loadConfig();
        if (!empty($config['server'])) {
            return $config['server'];
        }

        // Fallback: Read APP_URL from TrustNode .env file if available
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            if (preg_match('/^APP_URL=(.*)$/m', $envContent, $matches)) {
                $url = trim($matches[1]);
                if (!empty($url)) {
                    // In docker dev environment, nginx binds to port 8000 on host
                    $parsed = parse_url($url);
                    $host = $parsed['host'] ?? '';
                    if (($host === 'localhost' || $host === '127.0.0.1') && empty($parsed['port'])) {
                        return 'http://127.0.0.1:8000';
                    }
                    return $url;
                }
            }
        }

        return 'http://127.0.0.1:8000';
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

    public function getAgentId(): string
    {
        $config = $this->loadConfig();
        if (!empty($config['agent_id'])) {
            return $config['agent_id'];
        }

        $hostname = gethostname() ?: 'local';
        $cleanHost = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $hostname));
        $generated = 'agent-' . ($cleanHost ?: 'host') . '-' . substr(md5(php_uname('n') . $this->configDir), 0, 6);

        $this->saveAgentId($generated);
        return $generated;
    }

    public function saveAgentId(string $agentId): void
    {
        if (!is_dir($this->configDir)) {
            mkdir($this->configDir, 0700, true);
        }

        $config = $this->loadConfig();
        $config['agent_id'] = $agentId;
        file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT));
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
