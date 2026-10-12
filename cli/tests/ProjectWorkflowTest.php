<?php

namespace TrustNode\Cli\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TrustNode\Cli\Commands\ProjectAddCommand;
use TrustNode\Cli\Commands\ProjectListCommand;
use TrustNode\Cli\Commands\ProjectScanCommand;
use TrustNode\Cli\Commands\ScanStartCommand;
use TrustNode\Cli\Config\ConfigManager;
use TrustNode\Cli\Http\TrustNodeClient;

/**
 * CLI workflow against scripted API responses. The real command, client, error mapping and exit codes
 * run. Requests are recorded, so each test can check exactly what the CLI sent. No network is used.
 */
class ProjectWorkflowTest extends TestCase
{
    private const BASE = 'http://localhost:8000/';
    private const TOKEN = 'cli-test-token';

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function client(array $responses): TrustNodeClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $config = $this->createMock(ConfigManager::class);
        $config->method('getUrl')->willReturn(rtrim(self::BASE, '/'));
        $config->method('getToken')->willReturn(self::TOKEN);

        $client = new TrustNodeClient($config);
        // Same headers the real client builds from the stored token.
        $client->setClientForTesting(new Client([
            'base_uri' => self::BASE,
            'handler' => $stack,
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . self::TOKEN],
        ]));

        return $client;
    }

    private function runCommand(Command $command, array $input = []): array
    {
        $app = new Application();
        $app->add($command);
        $tester = new CommandTester($app->find($command->getName()));
        $code = $tester->execute($input);

        return [$code, $tester->getDisplay()];
    }

    private function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }

    private function sentRequests(): array
    {
        return array_map(fn ($h) => $h['request'], $this->history);
    }

    public function test_requests_carry_the_bearer_token_from_configuration(): void
    {
        $client = $this->client([$this->json(200, ['data' => []])]);

        $this->runCommand(new ProjectListCommand($client));

        $request = $this->sentRequests()[0];
        $this->assertSame('Bearer ' . self::TOKEN, $request->getHeaderLine('Authorization'));
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/projects', $request->getUri()->getPath());
    }

    public function test_project_list_prints_registered_projects(): void
    {
        $client = $this->client([$this->json(200, ['data' => [
            ['id' => 7, 'name' => 'Approved app', 'path' => 'approved-app', 'scan_profile' => 'standard', 'status' => 'active'],
        ]])]);

        [$code, $output] = $this->runCommand(new ProjectListCommand($client));

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Approved app', $output);
        $this->assertStringContainsString('approved-app', $output);
    }

    public function test_project_list_with_no_projects_explains_how_to_register(): void
    {
        $client = $this->client([$this->json(200, ['data' => []])]);

        [$code, $output] = $this->runCommand(new ProjectListCommand($client));

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('project:add', $output);
    }

    public function test_project_list_reports_unauthenticated_with_failure_exit_code(): void
    {
        $client = $this->client([$this->json(401, ['message' => 'Unauthenticated.'])]);

        [$code, $output] = $this->runCommand(new ProjectListCommand($client));

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Authentication required', $output);
    }

    public function test_project_scan_queues_by_id_and_sends_no_path(): void
    {
        $client = $this->client([$this->json(200, ['message' => 'Scan queued successfully.'])]);

        [$code, $output] = $this->runCommand(new ProjectScanCommand($client), ['id' => '7']);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Scan queued successfully.', $output);

        $request = $this->sentRequests()[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/projects/7/scan', $request->getUri()->getPath());
        // The client sends an empty JSON array for a request without data: no path, target or credentials.
        $this->assertSame([], json_decode((string) $request->getBody(), true), 'A project scan must not carry a path or target.');
    }

    public function test_project_scan_rejects_a_non_positive_id_without_contacting_the_server(): void
    {
        $client = $this->client([]);

        [$code, $output] = $this->runCommand(new ProjectScanCommand($client), ['id' => '0']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('positive integer', $output);
        $this->assertCount(0, $this->history);
    }

    public function test_project_scan_of_an_unregistered_or_out_of_scope_project_is_not_found(): void
    {
        // The server returns 404 for a project the user cannot see, so existence is not revealed.
        $client = $this->client([$this->json(404, ['message' => 'Not found.'])]);

        [$code, $output] = $this->runCommand(new ProjectScanCommand($client), ['id' => '99']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Resource not found', $output);
    }

    public function test_project_scan_reports_when_no_agent_is_running(): void
    {
        $client = $this->client([$this->json(503, ['message' => 'No active agent found to process the scan.'])]);

        [$code, $output] = $this->runCommand(new ProjectScanCommand($client), ['id' => '7']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Server is temporarily unavailable', $output);
    }

    public function test_project_add_sends_the_registration_fields_only(): void
    {
        $client = $this->client([$this->json(201, ['data' => ['id' => 12]])]);

        [$code, $output] = $this->runCommand(new ProjectAddCommand($client), [
            '--name' => 'Approved app',
            '--path' => 'approved-app',
            '--profile' => 'full',
            '--continuous' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringContainsString('Project ID: 12', $output);

        $request = $this->sentRequests()[0];
        $this->assertSame('/api/projects', $request->getUri()->getPath());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['name', 'path', 'scan_profile', 'continuous_scan_enabled'], array_keys($body));
        $this->assertSame('approved-app', $body['path']);
        $this->assertTrue($body['continuous_scan_enabled']);
    }

    public function test_project_add_requires_name_and_path_without_contacting_the_server(): void
    {
        $client = $this->client([]);

        [$code] = $this->runCommand(new ProjectAddCommand($client), ['--path' => 'approved-app']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertCount(0, $this->history);
    }

    public function test_project_add_surfaces_a_rejected_path_as_a_validation_failure(): void
    {
        $client = $this->client([$this->json(422, ['message' => 'The specified path resolves outside the allowed workspace mount.'])]);

        [$code, $output] = $this->runCommand(new ProjectAddCommand($client), ['--name' => 'x', '--path' => '../escape']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Validation failed', $output);
    }

    public function test_network_failure_is_reported_with_failure_exit_code(): void
    {
        $stack = HandlerStack::create(new MockHandler([
            new ConnectException('connection refused', new Request('GET', 'api/projects')),
        ]));
        $config = $this->createMock(ConfigManager::class);
        $config->method('getUrl')->willReturn('http://localhost:8000');
        $config->method('getToken')->willReturn(self::TOKEN);
        $client = new TrustNodeClient($config);
        $client->setClientForTesting(new Client(['base_uri' => self::BASE, 'handler' => $stack]));

        [$code, $output] = $this->runCommand(new ProjectListCommand($client));

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('Network or unexpected error', $output);
    }

    public function test_scan_start_refuses_to_run_without_an_explicit_target(): void
    {
        // Previously this sent the placeholder target "default-target".
        $client = $this->client([]);

        [$code, $output] = $this->runCommand(new ScanStartCommand($client), ['type' => 'repository']);

        $this->assertSame(Command::FAILURE, $code);
        $this->assertStringContainsString('--target is required', $output);
        $this->assertCount(0, $this->history);
    }
}
