<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'report', description: 'Request, check status, or download a report')]
class ReportCommand extends Command
{
    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action_or_scan_id', InputArgument::REQUIRED, 'Scan ID to generate report, or "status", or "download"');
        $this->addArgument('scan_id', InputArgument::OPTIONAL, 'Scan ID (if action is status or download)');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (only for download)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionOrId = $input->getArgument('action_or_scan_id');

        if ($actionOrId === 'status' || $actionOrId === 'download') {
            $scanId = $input->getArgument('scan_id');
            if (!$scanId) {
                $output->writeln('<error>Please provide a scan ID.</error>');
                return Command::FAILURE;
            }

            if ($actionOrId === 'status') {
                try {
                    $data = $this->client->get("api/scans/$scanId/report/status");
                    $status = $data['status'] ?? 'unknown';
                    $output->writeln("Scan ID: $scanId");
                    $output->writeln("Report Status: $status");
                    return Command::SUCCESS;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Resource not found') || str_contains($e->getMessage(), 'No report found')) {
                        $output->writeln("No report found for scan $scanId.");
                        return Command::FAILURE;
                    }
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                    return Command::FAILURE;
                }
            }

            if ($actionOrId === 'download') {
                try {
                    $statusData = $this->client->get("api/scans/$scanId/report/status");
                    $status = $statusData['status'] ?? 'unknown';
                    
                    if ($status !== 'completed') {
                        $output->writeln("Report is not ready yet.");
                        $output->writeln("Check progress:");
                        $output->writeln("trustnode report status $scanId");
                        return Command::FAILURE;
                    }
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Resource not found') || str_contains($e->getMessage(), 'No report found')) {
                        $output->writeln("No report found for scan $scanId.");
                        return Command::FAILURE;
                    }
                    $output->writeln('<error>' . $e->getMessage() . '</error>');
                    return Command::FAILURE;
                }

                $reportsDir = '/var/www/html/reports';
                if (!is_dir($reportsDir)) {
                    mkdir($reportsDir, 0755, true);
                }

                $outputPath = $input->getOption('output');
                $isCustomOutput = !empty($outputPath);
                if (!$outputPath) {
                    $outputPath = $reportsDir . DIRECTORY_SEPARATOR . "trustnode-report-$scanId.pdf";
                } elseif (!str_starts_with($outputPath, '/')) {
                    // if it's relative, force it inside reports dir so it's accessible
                    $outputPath = $reportsDir . DIRECTORY_SEPARATOR . $outputPath;
                }

                if (file_exists($outputPath)) {
                    if ($input->isInteractive()) {
                        $helper = $this->getHelper('question');
                        $question = new ConfirmationQuestion("File $outputPath already exists. Overwrite? [y/N] ", false);
                        if (!$helper->ask($input, $output, $question)) {
                            $output->writeln('<error>Download cancelled. File already exists.</error>');
                            return Command::FAILURE;
                        }
                    } else {
                        $output->writeln("<error>File $outputPath already exists. Cannot overwrite in non-interactive mode.</error>");
                        return Command::FAILURE;
                    }
                }

                try {
                    $this->client->download("api/scans/$scanId/report/download", $outputPath);
                    $output->writeln("Report downloaded successfully.");
                    $output->writeln("");
                    
                    $displayPath = $outputPath;
                    $hostDir = getenv('TRUSTNODE_HOST_DIR');
                    if ($hostDir && str_starts_with($outputPath, '/var/www/html/')) {
                        $relative = substr($outputPath, strlen('/var/www/html/'));
                        $sep = str_contains($hostDir, '\\') ? '\\' : '/';
                        $displayPath = rtrim($hostDir, '/\\') . $sep . str_replace('/', $sep, $relative);
                    }

                    $output->writeln("File:");
                    $output->writeln($displayPath);
                    return Command::SUCCESS;
                } catch (\Exception $e) {
                    if (str_contains($e->getMessage(), 'Resource not found') || str_contains($e->getMessage(), 'not available')) {
                        $output->writeln("Report is not ready yet.");
                        $output->writeln("Check progress:");
                        $output->writeln("trustnode report status $scanId");
                        return Command::FAILURE;
                    }
                    $output->writeln('<error>Failed to download report: ' . $e->getMessage() . '</error>');
                    return Command::FAILURE;
                }
            }
        }

        $scanId = $actionOrId;
        $output->writeln("<info>Report generation requested.</info>");
        try {
            $data = $this->client->post("api/scans/$scanId/report");
            $output->writeln("Report is queued asynchronously.");
            $output->writeln("Scan ID: $scanId");
            $output->writeln("Check progress using: trustnode report status $scanId");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
