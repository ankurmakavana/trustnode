<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Services\AgentDaemonManager;

#[AsCommand(name: 'agent:service', description: 'Manage persistent background TrustNode Local Agent daemon (install, start, stop, restart, status, uninstall)')]
class AgentServiceCommand extends Command
{
    public function __construct(
        private ConfigManager $configManager,
        private ?AgentDaemonManager $daemonManager = null
    ) {
        parent::__construct();
        $this->daemonManager = $daemonManager ?? new AgentDaemonManager($configManager);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'action',
            InputArgument::OPTIONAL,
            'Action to perform: install, start, stop, restart, status, uninstall',
            'status'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower((string) $input->getArgument('action'));
        $os = $this->daemonManager->getOsFamily();

        $output->writeln('<info>====================================================</info>');
        $output->writeln('<info>TrustNode Local Agent Service Manager</info>');
        $output->writeln("<info>Host OS:     {$os}</info>");
        $output->writeln("<info>Agent ID:    {$this->configManager->getAgentId()}</info>");
        $output->writeln('<info>====================================================</info>');

        switch ($action) {
            case 'install':
                $output->writeln('<comment>Installing persistent background agent...</comment>');
                $res = $this->daemonManager->install();
                if ($res['success']) {
                    $output->writeln("<info>{$res['message']}</info>");
                    $output->writeln('<comment>Starting agent in background...</comment>');
                    $startRes = $this->daemonManager->start();
                    $output->writeln("<info>{$startRes['message']}</info>");
                    return Command::SUCCESS;
                }
                $output->writeln("<error>Installation failed: {$res['message']}</error>");
                return Command::FAILURE;

            case 'start':
                $output->writeln('<comment>Starting background agent daemon...</comment>');
                $res = $this->daemonManager->start();
                if ($res['success']) {
                    $output->writeln("<info>{$res['message']}</info>");
                    return Command::SUCCESS;
                }
                $output->writeln("<error>{$res['message']}</error>");
                return Command::FAILURE;

            case 'stop':
                $output->writeln('<comment>Stopping background agent daemon...</comment>');
                $res = $this->daemonManager->stop();
                $output->writeln("<info>{$res['message']}</info>");
                return Command::SUCCESS;

            case 'restart':
                $output->writeln('<comment>Restarting background agent daemon...</comment>');
                $res = $this->daemonManager->restart();
                $output->writeln("<info>{$res['message']}</info>");
                return $res['success'] ? Command::SUCCESS : Command::FAILURE;

            case 'uninstall':
                $output->writeln('<comment>Uninstalling background agent service...</comment>');
                $res = $this->daemonManager->uninstall();
                $output->writeln("<info>{$res['message']}</info>");
                return Command::SUCCESS;

            case 'status':
            default:
                $running = $this->daemonManager->isRunning();
                $pid = $this->daemonManager->getPid();
                $installed = $this->daemonManager->isInstalled();

                $statusText = $running ? "<info>RUNNING (PID: {$pid})</info>" : '<comment>STOPPED</comment>';
                $installText = $installed ? '<info>INSTALLED (Auto-Start Enabled)</info>' : '<comment>NOT INSTALLED (Manual Start Only)</comment>';

                $output->writeln("Daemon Status:    {$statusText}");
                $output->writeln("Auto-Start:       {$installText}");
                $output->writeln("PHP Runtime:      {$this->daemonManager->getPhpBinary()}");
                $output->writeln("Runner Path:      {$this->daemonManager->getRunnerScriptPath()}");

                if (!$running) {
                    $output->writeln('');
                    $output->writeln('<comment>To install and start in the background: php cli/bin/trustnode agent:service install</comment>');
                    $output->writeln('<comment>To start once in the background:        php cli/bin/trustnode agent:service start</comment>');
                }

                return Command::SUCCESS;
        }
    }
}
