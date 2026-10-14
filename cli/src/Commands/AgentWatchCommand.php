<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;
use TrustNode\Cli\Services\DirectoryWatcher;
use TrustNode\Cli\Services\LocalDirectoryPackager;

#[AsCommand(name: 'agent:watch', description: 'Run the persistent TrustNode Local Agent to monitor and scan authorized directories')]
class AgentWatchCommand extends Command
{
    public function __construct(
        private TrustNodeClient $client,
        private ConfigManager $configManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Local project directory path to authorize and monitor directly');
        $this->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Project name');
        $this->addOption('agent-id', null, InputOption::VALUE_OPTIONAL, 'Explicit Agent identifier');
        $this->addOption('interval', null, InputOption::VALUE_OPTIONAL, 'Polling change detection interval in seconds', '5');
        $this->addOption('debounce', null, InputOption::VALUE_OPTIONAL, 'Debounce quiet window in seconds before trigger', '10');
        $this->addOption('schedule', null, InputOption::VALUE_OPTIONAL, 'Periodic scheduled scan interval in minutes', '30');
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run initial scan and exit without continuous monitoring');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pathArg = (string) $input->getOption('path');
        $agentId = (string) $input->getOption('agent-id');
        if ($agentId === '') {
            $agentId = $this->configManager->getAgentId();
        }

        $pollInterval = max(1, (int) $input->getOption('interval'));
        $debounceWindow = max(2, (int) $input->getOption('debounce'));
        $scheduleMinutes = max(1, (int) $input->getOption('schedule'));
        $scheduleSeconds = $scheduleMinutes * 60;
        $runOnce = (bool) $input->getOption('once');

        // Prevent duplicate instances if running as continuous daemon
        if (!$runOnce) {
            $pidFile = $this->configManager->getPidFile();
            if (file_exists($pidFile)) {
                $existingPid = (int) trim((string) file_get_contents($pidFile));
                if ($existingPid > 0 && $this->isProcessAlive($existingPid)) {
                    $output->writeln("<comment>TrustNode Local Agent daemon is already running (PID: {$existingPid}).</comment>");
                    return Command::SUCCESS;
                }
            }
            if (!is_dir($this->configManager->getConfigDir())) {
                mkdir($this->configManager->getConfigDir(), 0700, true);
            }
            file_put_contents($pidFile, (string) getmypid());
            register_shutdown_function(function () use ($pidFile) {
                if (file_exists($pidFile) && (int) trim((string) @file_get_contents($pidFile)) === getmypid()) {
                    @unlink($pidFile);
                }
            });
        }

        // If path is explicitly provided on command line, authorize it first
        if ($pathArg !== '') {
            $realPath = realpath($pathArg);
            if (!$realPath || !is_dir($realPath)) {
                $output->writeln("<error>Specified path does not exist or is not a directory: {$pathArg}</error>");
                return Command::FAILURE;
            }

            // System root protection check
            $normalizedPath = rtrim(str_replace('\\', '/', $realPath), '/');
            if (preg_match('/^[a-zA-Z]:$/', $normalizedPath) || $normalizedPath === '' || $normalizedPath === '/etc' || $normalizedPath === '/root') {
                $output->writeln('<error>Authorizing system root or whole drives is not permitted for security.</error>');
                return Command::FAILURE;
            }

            $projectName = $input->getOption('name') ?: basename($realPath);

            $output->writeln('<info>====================================================</info>');
            $output->writeln('<info>TrustNode Persistent Local Agent (Host Daemon)</info>');
            $output->writeln("<info>Agent ID:    {$agentId}</info>");
            $output->writeln("<info>Host OS:     " . PHP_OS_FAMILY . " (" . php_uname('s') . " " . php_uname('r') . ")</info>");
            $output->writeln("<info>Initial Dir: {$realPath}</info>");
            $output->writeln("<info>Project:     {$projectName}</info>");
            $output->writeln('<info>====================================================</info>');

            // Send initial heartbeat to register online
            $this->sendHeartbeat($agentId, $output);

            $output->writeln('<comment>[1/2] Authorizing project with TrustNode server...</comment>');
            $projectId = $this->authorizeProject($projectName, $realPath, $agentId, $output);
            if (!$projectId) {
                return Command::FAILURE;
            }

            $output->writeln('<comment>[2/2] Performing initial baseline scan...</comment>');
            $this->performScan($realPath, $projectName, $projectId, $agentId, $output);

            if ($runOnce) {
                $output->writeln('<info>Run-once specified. Agent finished.</info>');
                return Command::SUCCESS;
            }
        } else {
            $output->writeln('<info>====================================================</info>');
            $output->writeln('<info>TrustNode Persistent Local Agent (Host Daemon)</info>');
            $output->writeln("<info>Agent ID:    {$agentId}</info>");
            $output->writeln("<info>Host OS:     " . PHP_OS_FAMILY . " (" . php_uname('s') . " " . php_uname('r') . ")</info>");
            $output->writeln('<info>Status:      Online & listening for authorized project folders</info>');
            $output->writeln('<info>====================================================</info>');

            // Send initial heartbeat to register online
            $this->sendHeartbeat($agentId, $output);
        }

        // Multi-Project Persistent Monitoring Loop
        $watcher = new DirectoryWatcher();
        /** @var array<int, array{path: string, name: string, snapshot: array<string, string>, last_scan: int, pending_change_at: ?int, scanning: bool}> $monitoredProjects */
        $monitoredProjects = [];
        $lastHeartbeat = time();

        $output->writeln("<comment>Starting multi-project continuous monitoring (debounce: {$debounceWindow}s, poll: {$pollInterval}s)...</comment>");

        while (true) {
            $now = time();

            // Heartbeat & task sync every 5 seconds
            if ($now - $lastHeartbeat >= 5) {
                $res = $this->sendHeartbeatWithResult($agentId, $output);
                $lastHeartbeat = time();

                // 1. Check for immediate scan requests (e.g., initial scan after authorization)
                if (!empty($res['pending_scan_request'])) {
                    $scanReq = $res['pending_scan_request'];
                    $targetPath = $scanReq['target_path'] ?? null;
                    $projectId = (int) ($scanReq['project_id'] ?? 0);
                    $name = $scanReq['name'] ?? basename($targetPath ?? 'Project');

                    if ($targetPath && is_dir($targetPath) && $projectId > 0) {
                        $realPath = realpath($targetPath);
                        $output->writeln("<info>[Agent] Adopting project '{$name}' (#{$projectId}) at {$realPath}...</info>");

                        // Perform initial scan
                        $this->performScan($realPath, $name, $projectId, $agentId, $output);

                        if ($runOnce) {
                            return Command::SUCCESS;
                        }

                        // Register into multi-project monitoring map
                        $monitoredProjects[$projectId] = [
                            'path' => $realPath,
                            'name' => $name,
                            'snapshot' => $watcher->getSnapshot($realPath),
                            'last_scan' => time(),
                            'pending_change_at' => null,
                            'scanning' => false,
                        ];
                    }
                }

                // 2. Synchronize active assigned projects from server heartbeat
                if (isset($res['assigned_projects']) && is_array($res['assigned_projects'])) {
                    $activeServerIds = [];
                    foreach ($res['assigned_projects'] as $p) {
                        $pId = (int) ($p['id'] ?? 0);
                        $pPath = (string) ($p['path'] ?? '');
                        $pName = (string) ($p['name'] ?? 'Project');
                        $activeServerIds[] = $pId;

                        if ($pId > 0 && !isset($monitoredProjects[$pId])) {
                            $realP = realpath($pPath);
                            if ($realP && is_dir($realP)) {
                                $output->writeln("<info>[Agent] Synced active project '{$pName}' (#{$pId}) at {$realP} for monitoring.</info>");
                                $monitoredProjects[$pId] = [
                                    'path' => $realP,
                                    'name' => $pName,
                                    'snapshot' => $watcher->getSnapshot($realP),
                                    'last_scan' => time(),
                                    'pending_change_at' => null,
                                    'scanning' => false,
                                ];
                            } else {
                                $output->writeln("<comment>[Agent] Note: Authorized project #{$pId} path is not accessible on this host: {$pPath}</comment>");
                            }
                        }
                    }

                    // Remove projects that have been revoked or disabled on the server
                    foreach (array_keys($monitoredProjects) as $existingId) {
                        if (!in_array($existingId, $activeServerIds, true)) {
                            $output->writeln("<comment>[Agent] Project #{$existingId} no longer active or revoked on server. Ceasing monitoring.</comment>");
                            unset($monitoredProjects[$existingId]);
                        }
                    }
                }
            }

            // Iterate over all active monitored projects
            foreach ($monitoredProjects as $projectId => &$pData) {
                if ($pData['scanning']) {
                    continue;
                }

                $pPath = $pData['path'];
                $pName = $pData['name'];

                // Check periodic scheduled scan
                if ($now - $pData['last_scan'] >= $scheduleSeconds) {
                    $output->writeln("<info>[Schedule] Running scheduled scan for {$pName} (#{$projectId})...</info>");
                    $pData['scanning'] = true;
                    $this->performScan($pPath, $pName, $projectId, $agentId, $output);
                    $pData['scanning'] = false;
                    $pData['last_scan'] = time();
                    $pData['snapshot'] = $watcher->getSnapshot($pPath);
                    $pData['pending_change_at'] = null;
                    continue;
                }

                // Check for file modifications
                $currentSnapshot = $watcher->getSnapshot($pPath);
                $diff = $watcher->diff($pData['snapshot'], $currentSnapshot);

                if ($diff['changed']) {
                    if ($pData['pending_change_at'] === null) {
                        $pData['pending_change_at'] = time();
                        $output->writeln("<comment>[Change] Modifications in {$pName} (+{$diff['added']} ~{$diff['modified']} -{$diff['removed']}). Debouncing for {$debounceWindow}s...</comment>");
                    }
                    $pData['snapshot'] = $currentSnapshot;
                }

                // Trigger scan once debounce window elapses
                if ($pData['pending_change_at'] !== null && ($now - $pData['pending_change_at'] >= $debounceWindow)) {
                    $output->writeln("<info>[Trigger] Debounce elapsed for {$pName} (#{$projectId}). Starting change-triggered scan...</info>");
                    $pData['scanning'] = true;
                    $this->performScan($pPath, $pName, $projectId, $agentId, $output);
                    $pData['scanning'] = false;
                    $pData['pending_change_at'] = null;
                    $pData['last_scan'] = time();
                    $pData['snapshot'] = $watcher->getSnapshot($pPath);
                }
            }
            unset($pData);

            sleep($pollInterval);
        }

        return Command::SUCCESS;
    }

    private function sendHeartbeat(string $agentId, OutputInterface $output): void
    {
        $this->sendHeartbeatWithResult($agentId, $output);
    }

    private function sendHeartbeatWithResult(string $agentId, OutputInterface $output): array
    {
        try {
            $res = $this->client->post('api/agent/heartbeat', [
                'agent_id' => $agentId,
                'version' => '1.0.0',
                'os' => PHP_OS_FAMILY,
                'hostname' => gethostname() ?: 'unknown',
                'execution_mode' => 'desktop',
                'desktop_picker_supported' => true,
            ]);

            return is_array($res) ? $res : [];
        } catch (\Throwable $e) {
            $output->writeln("<error>Heartbeat error: " . $e->getMessage() . "</error>");
            return [];
        }
    }

    private function authorizeProject(string $name, string $path, string $agentId, OutputInterface $output): ?int
    {
        try {
            $res = $this->client->post('api/projects', [
                'name' => $name,
                'path' => $path,
                'source_type' => 'agent',
                'agent_id' => $agentId,
                'scan_profile' => 'standard',
                'continuous_scan_enabled' => true,
            ]);

            $id = $res['data']['id'] ?? null;
            $output->writeln("<info>Project registered and authorized successfully (ID: {$id}).</info>");
            return $id;
        } catch (\Throwable $e) {
            try {
                $list = $this->client->get('api/projects');
                foreach ($list['data'] ?? [] as $proj) {
                    if (($proj['path'] ?? '') === $path) {
                        $output->writeln("<info>Found existing authorized project (ID: {$proj['id']}).</info>");
                        return $proj['id'];
                    }
                }
            } catch (\Throwable) {}

            $output->writeln("<error>Failed to authorize project on server: " . $e->getMessage() . "</error>");
            return null;
        }
    }

    private function performScan(string $realPath, string $name, int $projectId, string $agentId, OutputInterface $output): void
    {
        $output->writeln("<info>Scanning local directory: {$realPath}</info>");
        $packager = new LocalDirectoryPackager();
        $tempZip = tempnam(sys_get_temp_dir(), 'tn_agent_') . '.zip';

        try {
            $summary = $packager->package($realPath, $tempZip);
            $output->writeln("<info>Packaged {$summary['files_count']} eligible files for scan ingestion (" . number_format($summary['total_size'] / 1024, 1) . " KB).</info>");

            // Send local scan package securely to backend
            $res = $this->client->postMultipart('api/scans/local', [
                [
                    'name' => 'archive',
                    'contents' => fopen($tempZip, 'r'),
                    'filename' => basename($tempZip),
                ],
                [
                    'name' => 'target',
                    'contents' => $name,
                ],
                [
                    'name' => 'project_id',
                    'contents' => (string) $projectId,
                ],
            ]);

            $scanId = $res['scan_id'] ?? $res['id'] ?? 'queued';
            $output->writeln("<info>Scan queued successfully! Scan ID: {$scanId}</info>");
        } catch (\Throwable $e) {
            $output->writeln("<error>Scan failed: " . $e->getMessage() . "</error>");
        } finally {
            if (file_exists($tempZip)) {
                @unlink($tempZip);
            }
        }
    }

    private function isProcessAlive(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
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
