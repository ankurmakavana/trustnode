<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'report:download', description: 'Download report for a scan')]
class ReportDownloadCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Scan ID');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $outputPath = $input->getOption('output') ?: "trustnode-scan-{$id}-report.pdf";
        $force = $input->getOption('force');

        if (file_exists($outputPath) && !$force) {
            if ($input->isInteractive()) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion("File {$outputPath} already exists. Overwrite? (y/N) ", false);
                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('Download aborted.');
                    return Command::SUCCESS;
                }
            } else {
                $output->writeln("<error>File {$outputPath} already exists. Use --force to overwrite.</error>");
                return Command::FAILURE;
            }
        }

        try {
            // Check status first to prevent starting download if not ready
            $data = $this->client->get("api/scans/{$id}");
            $scan = $data['data'] ?? [];
            if (($scan['report_status'] ?? '') === 'generating') {
                $output->writeln('<error>Report is still generating.</error>');
                return Command::FAILURE;
            }

            $output->writeln("Downloading report to {$outputPath}...");
            $this->client->download("api/reports/download?scan_id={$id}", $outputPath);
            
            $output->writeln("<info>Report successfully downloaded to {$outputPath}</info>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            // Clean up partial file if download failed
            if (file_exists($outputPath) && filesize($outputPath) === 0) {
                @unlink($outputPath);
            }
            return Command::FAILURE;
        }
    }
}
