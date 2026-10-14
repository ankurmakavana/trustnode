<?php

namespace Tests\Unit;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Services\AgentDaemonManager;

class AgentDaemonManagerTest extends TestCase
{
    private string $tempHome;
    private ConfigManager $configManager;
    private AgentDaemonManager $daemonManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tn_daemon_test_' . uniqid();
        mkdir($this->tempHome, 0777, true);

        // Point ConfigManager home to temporary directory
        $this->configManager = new ConfigManager($this->tempHome);
        $this->daemonManager = new AgentDaemonManager($this->configManager);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempHome);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_get_os_family_returns_valid_supported_os(): void
    {
        $os = strtolower($this->daemonManager->getOsFamily());
        $this->assertContains($os, ['windows', 'darwin', 'linux']);
    }

    public function test_get_php_binary_and_cli_script_path_exist(): void
    {
        $php = $this->daemonManager->getPhpBinary();
        $this->assertNotEmpty($php);

        $cli = $this->daemonManager->getCliScriptPath();
        $this->assertFileExists($cli);
    }

    public function test_pid_lifecycle_management(): void
    {
        $this->assertFalse($this->daemonManager->isRunning());
        $this->assertNull($this->daemonManager->getPid());

        // Write a test PID that does not exist
        $pidFile = $this->configManager->getPidFile();
        if (!is_dir(dirname($pidFile))) {
            mkdir(dirname($pidFile), 0777, true);
        }
        file_put_contents($pidFile, '99999999');

        // getPid() only returns the PID if the process is alive; non-existent PID returns null
        $this->assertNull($this->daemonManager->getPid());
        $this->assertFalse($this->daemonManager->isRunning());

        // Stop clears the PID file
        $this->daemonManager->stop();
        $this->assertFileDoesNotExist($pidFile);
        $this->assertFalse($this->daemonManager->isRunning());
        $this->assertNull($this->daemonManager->getPid());
    }

    public function test_status_returns_structured_metadata(): void
    {
        $status = $this->daemonManager->status();

        $this->assertArrayHasKey('os', $status);
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('pid', $status);
        $this->assertArrayHasKey('installed', $status);
        $this->assertArrayHasKey('auto_start', $status);
        $this->assertArrayHasKey('runner_path', $status);
        $this->assertArrayHasKey('php_binary', $status);
    }
}
