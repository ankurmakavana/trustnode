<?php

namespace TrustNode\Cli\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use TrustNode\Cli\Commands\LoginCommand;
use TrustNode\Cli\Commands\ScanStartCommand;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;

class CliTest extends TestCase
{
    private $clientMock;
    private $configMock;
    private $application;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(TrustNodeClient::class);
        $this->configMock = $this->createMock(ConfigManager::class);

        $this->application = new Application();
        $this->application->add(new LoginCommand($this->clientMock, $this->configMock));
        $this->application->add(new ScanStartCommand($this->clientMock));
    }

    public function test_login_failure_invalid_url(): void
    {
        // Testing login failure when URL/token is invalid
        // Since login instantiates a temporary Guzzle Client, we actually need to intercept it,
        // or just test that the command outputs an error if URL is empty.
        $command = $this->application->find('login');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['', '']);
        $commandTester->execute([]);

        $this->assertStringContainsString('URL and Token are required.', $commandTester->getDisplay());
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function test_scan_start_database_credentials_not_leaked(): void
    {
        $command = $this->application->find('scan:start');
        $commandTester = new CommandTester($command);

        // We expect the client to be called once with POST
        $this->clientMock->expects($this->once())
            ->method('post')
            ->willReturn(['data' => ['id' => 99]]);

        // We simulate answering the hidden password prompt
        $commandTester->setInputs(['SUPER_SECRET_DATABASE_PASSWORD']);
        
        $commandTester->execute([
            'type' => 'database',
            '--target' => 'mysql://test',
        ]);

        $output = $commandTester->getDisplay();
        
        // Assert password doesn't leak
        $this->assertStringNotContainsString('SUPER_SECRET_DATABASE_PASSWORD', $output);
        $this->assertStringContainsString('Scan started successfully.', $output);
        $this->assertStringContainsString('Scan ID: 99', $output);
    }
}
