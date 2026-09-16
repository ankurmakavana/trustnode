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
    protected function tearDown(): void
    {
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

    public function test_unchanged_state_does_not_enqueue_tasks()
    {
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        $tempFile = storage_path('app/temp_env_test');
        file_put_contents($tempFile, 'TEST=123');
        $hash = hash('sha256', 'TEST=123');
        
        Cache::put('agent_observe_env_hash_' . md5($tempFile), $hash);
        
        // Ensure no methods are called on scanner or queue
        $scanner->shouldNotReceive('scan');
        $queue->shouldNotReceive('enqueue');
        
        $handler->handle([
            'id' => '123',
            'payload' => ['target' => $tempFile]
        ]);
        
        @unlink($tempFile);
    }

    public function test_changed_state_enqueues_finding_tasks()
    {
        $scanner = Mockery::mock(SecretScanner::class);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        
        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        
        $tempFile = storage_path('app/temp_env_test2');
        file_put_contents($tempFile, 'SECRET=AWS_KEY_A3T1234567890123456');
        
        // Ensure cache doesn't have the hash
        Cache::forget('agent_observe_env_hash_' . md5($tempFile));
        
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
            
        $handler->handle([
            'id' => '123',
            'payload' => ['target' => $tempFile]
        ]);
        
        // Cache should be updated
        $this->assertEquals(hash('sha256', 'SECRET=AWS_KEY_A3T1234567890123456'), Cache::get('agent_observe_env_hash_' . md5($tempFile)));
        
        @unlink($tempFile);
    }
}
