<?php

namespace TrustNode\Cli\Services;

use TrustNode\Cli\Config\ConfigManager;

class AgentDaemonManager
{
    private string $osFamily;
    private ConfigManager $configManager;

    public function __construct(ConfigManager $configManager, ?string $osFamily = null)
    {
        $this->configManager = $configManager;
        $this->osFamily = $osFamily ?? PHP_OS_FAMILY;
    }

    public function getOsFamily(): string
    {
        return $this->osFamily;
    }

    /**
     * Resolve path to the PHP binary executing on the host.
     */
    public function getPhpBinary(): string
    {
        if (defined('PHP_BINARY') && file_exists(PHP_BINARY)) {
            return PHP_BINARY;
        }

        if ($this->osFamily === 'Windows') {
            $which = trim((string) @shell_exec('where.exe php 2>NUL'));
            if ($which !== '') {
                $lines = explode("\n", str_replace("\r", "", $which));
                return $lines[0];
            }
            return 'php.exe';
        }

        $which = trim((string) @shell_exec('which php 2>/dev/null'));
        return $which !== '' ? $which : 'php';
    }

    /**
     * Resolve path to the TrustNode CLI script.
     */
    public function getCliScriptPath(): string
    {
        $candidate = realpath(__DIR__ . '/../../../cli/bin/trustnode');
        if ($candidate && file_exists($candidate)) {
            return $candidate;
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'trustnode';
    }

    /**
     * Get path to background runner script in ~/.trustnode/
     */
    public function getRunnerScriptPath(): string
    {
        $configDir = $this->configManager->getConfigDir();
        if ($this->osFamily === 'Windows') {
            return $configDir . DIRECTORY_SEPARATOR . 'trustnode-agent-runner.vbs';
        }
        return $configDir . DIRECTORY_SEPARATOR . 'trustnode-agent-runner.sh';
    }

    /**
     * Check if background agent is currently running.
     */
    public function isRunning(): bool
    {
        $pidFile = $this->configManager->getPidFile();
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid <= 0) {
            return false;
        }

        return $this->isProcessAlive($pid);
    }

    /**
     * Get current running PID or null.
     */
    public function getPid(): ?int
    {
        $pidFile = $this->configManager->getPidFile();
        if (!file_exists($pidFile)) {
            return null;
        }

        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid > 0 && $this->isProcessAlive($pid)) {
            return $pid;
        }

        return null;
    }

    /**
     * Check if the persistent auto-start service/startup is configured.
     */
    public function isInstalled(): bool
    {
        if ($this->osFamily === 'Windows') {
            $cmd = 'reg query "HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" /v TrustNodeLocalAgent 2>NUL';
            $res = @shell_exec($cmd);
            return is_string($res) && str_contains($res, 'TrustNodeLocalAgent');
        }

        if ($this->osFamily === 'Darwin') {
            $plist = $this->getMacLaunchAgentPath();
            return file_exists($plist);
        }

        $service = $this->getLinuxSystemdServicePath();
        return file_exists($service);
    }

    /**
     * Return comprehensive status array.
     *
     * @return array{os: string, running: bool, pid: ?int, installed: bool, auto_start: bool, runner_path: string, php_binary: string}
     */
    public function status(): array
    {
        $running = $this->isRunning();
        $installed = $this->isInstalled();

        return [
            'os' => $this->osFamily,
            'running' => $running,
            'pid' => $this->getPid(),
            'installed' => $installed,
            'auto_start' => $installed,
            'runner_path' => $this->getRunnerScriptPath(),
            'php_binary' => $this->getPhpBinary(),
        ];
    }

    /**
     * Install one-time auto-start and write runner script.
     *
     * @return array{success: bool, message: string}
     */
    public function install(): array
    {
        $configDir = $this->configManager->getConfigDir();
        if (!is_dir($configDir)) {
            mkdir($configDir, 0700, true);
        }

        $php = $this->getPhpBinary();
        $cli = $this->getCliScriptPath();
        $logFile = $configDir . DIRECTORY_SEPARATOR . 'agent.log';

        if ($this->osFamily === 'Windows') {
            // Write VBScript runner that launches php in the background with window hidden (0)
            $vbsPath = str_replace('/', '\\', $this->getRunnerScriptPath());
            $batchPath = str_replace('/', '\\', $configDir . DIRECTORY_SEPARATOR . 'trustnode-agent-runner.bat');
            $logFileWin = str_replace('/', '\\', $logFile);
            $phpWin = str_replace('/', '\\', $php);
            $cliWin = str_replace('/', '\\', $cli);
            
            $batchContent = "@echo off\r\n\"{$phpWin}\" \"{$cliWin}\" agent:watch >> \"{$logFileWin}\" 2>&1\r\n";
            file_put_contents($batchPath, $batchContent);

            $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\r\nWshShell.Run \"cmd.exe /c \"\"{$batchPath}\"\"\", 0, False\r\n";
            file_put_contents($vbsPath, $vbsContent);

            // Register per-user Run entry in HKCU registry (no elevation required)
            $regCmd = "reg add \"HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run\" /v TrustNodeLocalAgent /t REG_SZ /d \"wscript.exe \\\"{$vbsPath}\\\"\" /f 2>NUL";
            @shell_exec($regCmd);

            return [
                'success' => true,
                'message' => 'TrustNode Local Agent successfully installed into per-user Windows Startup.',
            ];
        }

        if ($this->osFamily === 'Darwin') {
            $plistPath = $this->getMacLaunchAgentPath();
            $plistDir = dirname($plistPath);
            if (!is_dir($plistDir)) {
                @mkdir($plistDir, 0755, true);
            }

            $plistContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.trustnode.agent</string>
    <key>ProgramArguments</key>
    <array>
        <string>{$php}</string>
        <string>{$cli}</string>
        <string>agent:watch</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>{$logFile}</string>
    <key>StandardErrorPath</key>
    <string>{$logFile}</string>
</dict>
</plist>
XML;
            file_put_contents($plistPath, $plistContent);
            @shell_exec("launchctl load -w \"{$plistPath}\" 2>/dev/null");

            return [
                'success' => true,
                'message' => 'TrustNode Local Agent successfully installed as macOS LaunchAgent.',
            ];
        }

        // Linux per-user systemd service
        $servicePath = $this->getLinuxSystemdServicePath();
        $serviceDir = dirname($servicePath);
        if (!is_dir($serviceDir)) {
            @mkdir($serviceDir, 0755, true);
        }

        $serviceContent = <<<INI
[Unit]
Description=TrustNode Continuous Security & Remediation Local Agent
After=network.target

[Service]
Type=simple
ExecStart={$php} {$cli} agent:watch
Restart=on-failure
RestartSec=5
StandardOutput=append:{$logFile}
StandardError=append:{$logFile}

[Install]
WantedBy=default.target
INI;

        file_put_contents($servicePath, $serviceContent);
        @shell_exec('systemctl --user daemon-reload 2>/dev/null');
        @shell_exec('systemctl --user enable trustnode-agent.service 2>/dev/null');

        return [
            'success' => true,
            'message' => 'TrustNode Local Agent successfully installed as Linux per-user systemd service.',
        ];
    }

    /**
     * Start the background agent immediately without a visible window.
     *
     * @return array{success: bool, message: string}
     */
    public function start(): array
    {
        if ($this->isRunning()) {
            return [
                'success' => true,
                'message' => "Agent daemon is already running (PID: {$this->getPid()}).",
            ];
        }

        $configDir = $this->configManager->getConfigDir();
        if (!is_dir($configDir)) {
            mkdir($configDir, 0700, true);
        }

        $php = $this->getPhpBinary();
        $cli = $this->getCliScriptPath();
        $logFile = $configDir . DIRECTORY_SEPARATOR . 'agent.log';

        if ($this->osFamily === 'Windows') {
            $runnerPath = str_replace('/', '\\', $this->getRunnerScriptPath());
            if (!file_exists($runnerPath)) {
                $this->install();
            }

            // Launch silently in background using PowerShell Start-Process without opening a visible console window
            $logFileWin = str_replace('/', '\\', $logFile);
            $errFileWin = str_replace('/', '\\', $configDir . DIRECTORY_SEPARATOR . 'agent_err.log');
            $phpWin = str_replace('/', '\\', $php);
            $cliWin = str_replace('/', '\\', $cli);
            $workingDir = str_replace('/', '\\', realpath(dirname(__DIR__, 3)) ?: getcwd());
            $psCmd = "powershell.exe -NoProfile -NonInteractive -WindowStyle Hidden -Command \"Start-Process -FilePath '{$phpWin}' -ArgumentList 'cli\\bin\\trustnode', 'agent:watch' -WorkingDirectory '{$workingDir}' -WindowStyle Hidden -RedirectStandardOutput '{$logFileWin}' -RedirectStandardError '{$errFileWin}'\"";
            pclose(popen("start /min {$psCmd}", "r"));

            // Wait briefly for PID file
            $attempts = 0;
            while ($attempts < 10 && !$this->isRunning()) {
                usleep(200000); // 200ms
                $attempts++;
            }

            return [
                'success' => $this->isRunning(),
                'message' => $this->isRunning() ? 'TrustNode Local Agent started in background.' : 'Agent process initiated.',
            ];
        }

        if ($this->osFamily === 'Darwin') {
            $plistPath = $this->getMacLaunchAgentPath();
            if (file_exists($plistPath)) {
                @shell_exec("launchctl start com.trustnode.agent 2>/dev/null");
            } else {
                @shell_exec("nohup \"{$php}\" \"{$cli}\" agent:watch >> \"{$logFile}\" 2>&1 &");
            }

            return [
                'success' => true,
                'message' => 'TrustNode Local Agent started.',
            ];
        }

        // Linux
        $servicePath = $this->getLinuxSystemdServicePath();
        if (file_exists($servicePath)) {
            @shell_exec('systemctl --user start trustnode-agent.service 2>/dev/null');
        } else {
            @shell_exec("nohup \"{$php}\" \"{$cli}\" agent:watch >> \"{$logFile}\" 2>&1 &");
        }

        return [
            'success' => true,
            'message' => 'TrustNode Local Agent started.',
        ];
    }

    /**
     * Stop the running background agent process.
     *
     * @return array{success: bool, message: string}
     */
    public function stop(): array
    {
        $pid = $this->getPid();
        if (!$pid) {
            $pidFile = $this->configManager->getPidFile();
            if (file_exists($pidFile)) {
                @unlink($pidFile);
            }
            return [
                'success' => true,
                'message' => 'Agent is not currently running.',
            ];
        }

        if ($this->osFamily === 'Windows') {
            @shell_exec("taskkill /F /PID {$pid} 2>NUL");
        } else {
            if (function_exists('posix_kill')) {
                @posix_kill($pid, SIGTERM);
            } else {
                @shell_exec("kill -15 {$pid} 2>/dev/null");
            }
        }

        // Wait up to 3 seconds for termination
        $attempts = 0;
        while ($attempts < 15 && $this->isProcessAlive($pid)) {
            usleep(200000);
            $attempts++;
        }

        $pidFile = $this->configManager->getPidFile();
        if (file_exists($pidFile)) {
            @unlink($pidFile);
        }

        return [
            'success' => !$this->isProcessAlive($pid),
            'message' => 'TrustNode Local Agent daemon stopped successfully.',
        ];
    }

    /**
     * Restart the background agent daemon.
     *
     * @return array{success: bool, message: string}
     */
    public function restart(): array
    {
        $this->stop();
        usleep(500000); // 500ms
        return $this->start();
    }

    /**
     * Uninstall auto-start startup mechanism.
     *
     * @return array{success: bool, message: string}
     */
    public function uninstall(): array
    {
        $this->stop();

        if ($this->osFamily === 'Windows') {
            @shell_exec('reg delete "HKCU\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" /v TrustNodeLocalAgent /f 2>NUL');
            $vbs = $this->getRunnerScriptPath();
            if (file_exists($vbs)) {
                @unlink($vbs);
            }
            $bat = $this->configManager->getConfigDir() . DIRECTORY_SEPARATOR . 'trustnode-agent-runner.bat';
            if (file_exists($bat)) {
                @unlink($bat);
            }

            return [
                'success' => true,
                'message' => 'TrustNode Local Agent uninstalled from Windows Startup.',
            ];
        }

        if ($this->osFamily === 'Darwin') {
            $plist = $this->getMacLaunchAgentPath();
            if (file_exists($plist)) {
                @shell_exec("launchctl unload -w \"{$plist}\" 2>/dev/null");
                @unlink($plist);
            }

            return [
                'success' => true,
                'message' => 'TrustNode Local Agent uninstalled from macOS LaunchAgent.',
            ];
        }

        // Linux
        $service = $this->getLinuxSystemdServicePath();
        if (file_exists($service)) {
            @shell_exec('systemctl --user stop trustnode-agent.service 2>/dev/null');
            @shell_exec('systemctl --user disable trustnode-agent.service 2>/dev/null');
            @unlink($service);
            @shell_exec('systemctl --user daemon-reload 2>/dev/null');
        }

        return [
            'success' => true,
            'message' => 'TrustNode Local Agent uninstalled from Linux systemd user services.',
        ];
    }

    private function getMacLaunchAgentPath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        return $home . '/Library/LaunchAgents/com.trustnode.agent.plist';
    }

    private function getLinuxSystemdServicePath(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');
        return $home . '/.config/systemd/user/trustnode-agent.service';
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($this->osFamily === 'Windows') {
            $check = @shell_exec("tasklist /FI \"PID eq {$pid}\" 2>NUL");
            return is_string($check) && str_contains($check, (string) $pid);
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $check = @shell_exec("ps -p {$pid} 2>/dev/null");
        return is_string($check) && str_contains($check, (string) $pid);
    }
}
