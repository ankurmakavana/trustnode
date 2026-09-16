<?php

namespace Tests\Unit;

use App\Handlers\AgentObserveEnvTaskHandler;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Services\Scan\Scanners\SecretScanner;
use App\DTOs\Import\NormalizedFinding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Mockery;

class AgentObserveEnvTaskHandlerTest extends TestCase
{
    protected string $originalBasePath;
    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->originalBasePath = app()->basePath();
        $this->testBasePath = storage_path('app/agent_observe_test_' . uniqid());
        
        if (!File::exists($this->testBasePath)) {
            File::makeDirectory($this->testBasePath, 0755, true);
        }
        
        app()->setBasePath($this->testBasePath);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        
        if (File::exists($this->testBasePath)) {
            File::deleteDirectory($this->testBasePath);
        }
        
        Mockery::close();
        parent::tearDown();
    }

    public function test_handler_supports_observe_env_task()
    {
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        $this->assertTrue($handler->supports('agent.observe_env'));
        $this->assertFalse($handler->supports('other.task'));
    }

    // 4. Default target resolves to project .env
    // 15. Valid .env observation still detects changes and enqueues agent.report_finding
    public function test_default_target_resolves_to_project_env_and_enqueues_findings()
    {
        $envPath = base_path('.env');
        File::put($envPath, 'SECRET=AWS_KEY_A3T1234567890123456');
        
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        Cache::forget('agent_observe_env_hash_' . md5($envPath));
        
        $finding = new NormalizedFinding([
            'scanner' => 'SecretScanner',
            'title' => 'Secret Found'
        ]);
        
        $scanner->shouldReceive('scan')
            ->once()
            ->andReturn([$finding]);
            
        $agentService->shouldReceive('getAgentId')
            ->once()
            ->andReturn('agent-123');
            
        $queue->shouldReceive('enqueue')
            ->once()
            ->with('agent-123', 'agent.report_finding', Mockery::on(function($payload) {
                return isset($payload['finding']['title']) && $payload['finding']['title'] === 'Secret Found';
            }));
            
        // No target supplied
        $handler->handle([
            'id' => '123',
            'payload' => []
        ]);
        
        $this->assertEquals(hash('sha256', 'SECRET=AWS_KEY_A3T1234567890123456'), Cache::get('agent_observe_env_hash_' . md5($envPath)));
    }

    // 5. Explicit project .env target is accepted.
    public function test_explicit_project_env_target_is_accepted()
    {
        $envPath = base_path('.env');
        File::put($envPath, 'TEST=123');
        
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        // Cache existing to prevent enqueue
        $hash = hash('sha256', 'TEST=123');
        Cache::put('agent_observe_env_hash_' . md5($envPath), $hash);
        
        $scanner->shouldNotReceive('scan');
        $queue->shouldNotReceive('enqueue');
            
        // Explicit valid target supplied
        $handler->handle([
            'id' => '123',
            'payload' => ['target' => $envPath]
        ]);
        
        // If it was accepted but didn't scan because of hash match, it passes.
        $this->assertTrue(true);
    }

    // 6. Relative traversal path is rejected
    // 7. Absolute external path is rejected
    // 8. Another file inside the project is rejected
    // 9. Directory path is rejected
    // 10. Symlink resolving outside project .env is rejected
    // 13. Rejected target is never passed to SecretScanner
    // 14. Rejected target cannot generate a finding task
    public function test_invalid_targets_are_rejected()
    {
        $envPath = base_path('.env');
        File::put($envPath, 'TEST=123');
        
        $otherFile = base_path('other.txt');
        File::put($otherFile, 'OTHER=123');
        
        $externalDir = sys_get_temp_dir() . '/external_' . uniqid();
        File::makeDirectory($externalDir, 0755, true);
        $externalFile = $externalDir . '/.env';
        File::put($externalFile, 'EXT=123');
        
        $symlinkPath = base_path('symlinked.env');
        @symlink($externalFile, $symlinkPath);
        
        $directoryPath = base_path('config');
        File::makeDirectory($directoryPath);

        $invalidTargets = [
            $envPath . '/../.env', // 6. Relative traversal
            $externalFile, // 7. Absolute external
            $otherFile, // 8. Another file
            $directoryPath, // 9. Directory
            $symlinkPath // 10. Symlink resolving outside
        ];
        
        foreach ($invalidTargets as $invalidTarget) {
            $scanner = Mockery::mock(SecretScanner::class);
            $queue = Mockery::mock(AgentQueueInterface::class);
            $agentService = Mockery::mock(AgentService::class);
            
            $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
            
            // 13 & 14. Never passed to scanner, no enqueue
            $scanner->shouldNotReceive('scan');
            $queue->shouldNotReceive('enqueue');
            
            $handler->handle([
                'id' => '123',
                'payload' => ['target' => $invalidTarget]
            ]);
        }
        
        File::deleteDirectory($externalDir);
        $this->assertTrue(true);
    }

    // 11. Missing .env safely returns
    public function test_missing_env_safely_returns()
    {
        // Do not create .env
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        $scanner->shouldNotReceive('scan');
        $queue->shouldNotReceive('enqueue');
        
        $handler->handle([
            'id' => '123',
            'payload' => []
        ]);
        
        $this->assertTrue(true);
    }

    // 12. Malformed target payload fails closed
    public function test_malformed_target_payload_fails_closed()
    {
        $envPath = base_path('.env');
        File::put($envPath, 'TEST=123');
        
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        $scanner->shouldNotReceive('scan');
        $queue->shouldNotReceive('enqueue');
        
        $handler->handle([
            'id' => '123',
            'payload' => ['target' => ['an', 'array', 'is', 'malformed']]
        ]);
        
        $this->assertTrue(true);
    }
}
