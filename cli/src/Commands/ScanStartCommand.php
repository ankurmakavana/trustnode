<?php

namespace TrustNode\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use TrustNode\Cli\Http\TrustNodeClient;

#[\Symfony\Component\Console\Attribute\AsCommand(name: 'scan:start', description: 'Start a new scan')]
class ScanStartCommand extends Command
{
    
    

    public function __construct(private TrustNodeClient $client)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('type', InputArgument::REQUIRED, 'Scan type (repository, infrastructure, database)');
        $this->addOption('target', 't', InputOption::VALUE_REQUIRED, 'Target to scan');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Scan name');
        
        // Database credentials options
        $this->addOption('db-driver', null, InputOption::VALUE_OPTIONAL, 'Database driver (mysql, pgsql, etc)');
        $this->addOption('db-host', null, InputOption::VALUE_OPTIONAL, 'Database host');
        $this->addOption('db-port', null, InputOption::VALUE_OPTIONAL, 'Database port');
        $this->addOption('db-name', null, InputOption::VALUE_OPTIONAL, 'Database name');
        $this->addOption('db-user', null, InputOption::VALUE_OPTIONAL, 'Database username');
        
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = strtolower($input->getArgument('type'));
        $target = $input->getOption('target');
        $name = $input->getOption('name');

        if (!$target) {
            $target = 'default-target'; // or throw error
        }

        $scanType = '';
        $scanEngine = '';
        $credentials = null;

        if ($type === 'repository') {
            $scanType = 'repository';
            $scanEngine = 'repositoryscanner';
        } elseif ($type === 'infrastructure') {
            $scanType = 'network_ip';
            $scanEngine = 'nmap';
        } elseif ($type === 'database') {
            $scanType = 'database';
            $scanEngine = 'databasescanner';
            
            $dbDriver = $input->getOption('db-driver') ?? 'mysql';
            $dbHost = $input->getOption('db-host') ?? '127.0.0.1';
            $dbPort = $input->getOption('db-port') ?? 3306;
            $dbName = $input->getOption('db-name') ?? 'test';
            $dbUser = $input->getOption('db-user') ?? 'root';
            
            $password = $this->getDatabasePassword($input, $output);
            
            $credentials = [
                'driver' => $dbDriver,
                'host' => $dbHost,
                'port' => (int)$dbPort,
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $password,
            ];
            
            // Clear password variable as requested
            unset($password);
        } else {
            $output->writeln("<error>Unknown scan type: $type. Valid types: repository, infrastructure, database.</error>");
            return Command::FAILURE;
        }

        $payload = [
            'name' => $name ?: ucfirst($type) . ' Scan ' . date('Y-m-d H:i:s'),
            'type' => $scanType,
            'engine' => $scanEngine,
            'target' => $target,
        ];
        
        if ($credentials !== null) {
            $payload['credentials'] = $credentials;
        }

        try {
            $data = $this->client->post('api/scans', $payload);
            
            // Clear credentials payload memory
            if (isset($payload['credentials'])) {
                unset($payload['credentials']['password']);
                unset($payload['credentials']);
            }
            unset($credentials);
            
            if ($input->getOption('json')) {
                $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }
            
            $scanId = $data['data']['id'] ?? 'unknown';
            $output->writeln("<info>Scan started successfully.</info>");
            $output->writeln("Scan ID: $scanId");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function getDatabasePassword(InputInterface $input, OutputInterface $output): ?string
    {
        // 1. Check STDIN (if piped)
        if (0 === ftell(STDIN)) {
            $read = [STDIN];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0) === 1) {
                $stdinPassword = trim(stream_get_contents(STDIN));
                if ($stdinPassword !== '') {
                    return $stdinPassword;
                }
            }
        }
        
        // 2. Check Environment Variable
        if ($envPass = getenv('TRUSTNODE_DB_PASSWORD')) {
            return $envPass;
        }
        
        // 3. Interactive prompt
        if ($input->isInteractive()) {
            $helper = $this->getHelper('question');
            $question = new Question('Database password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            
            return $helper->ask($input, $output, $question);
        }
        
        return null; // Some databases don't require passwords
    }
}
