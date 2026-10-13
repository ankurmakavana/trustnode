<?php

namespace Tests\Unit;

use App\Contracts\AgentQueueInterface;
use App\Handlers\AgentObserveEnvTaskHandler;
use App\Services\AgentObserveEnvPolicy;
use App\Services\AgentScheduledTasks;
use App\Services\AgentService;
use App\Services\Scan\Scanners\SecretScanner;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

/**
 * Fail-closed guard for the .env observer (agent.observe_env).
 *
 * The observer must stay off unless agent.observe_env.enabled is exactly true. It is independent of
 * agent.continuous_scan. These tests cover the config parse, the handler (queued tasks), and the
 * enqueue chokepoint. Values are synthetic.
 */
class AgentObserveEnvGuardTest extends TestCase
{
    private const ENV_KEY = 'AGENT_OBSERVE_ENV_ENABLED';

    private string $originalBasePath;
    private ?string $tempBase = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBasePath = app()->basePath();
        TenantContext::setUserId(1);
    }

    protected function tearDown(): void
    {
        app()->setBasePath($this->originalBasePath);
        if ($this->tempBase !== null && File::exists($this->tempBase)) {
            File::deleteDirectory($this->tempBase);
        }
        TenantContext::clear();
        Mockery::close();
        parent::tearDown();
    }

    private function setEnvValue(?string $value): void
    {
        if ($value === null) {
            unset($_ENV[self::ENV_KEY], $_SERVER[self::ENV_KEY]);
            putenv(self::ENV_KEY);

            return;
        }

        $_ENV[self::ENV_KEY] = $value;
        $_SERVER[self::ENV_KEY] = $value;
        putenv(self::ENV_KEY . '=' . $value);
    }

    /** Reads the flag exactly as config/agent.php does, from a given environment value. */
    private function flagFromConfigFile(?string $envValue): mixed
    {
        $original = $_ENV[self::ENV_KEY] ?? null;

        try {
            $this->setEnvValue($envValue);
            $config = require base_path('config/agent.php');

            return $config['observe_env']['enabled'];
        } finally {
            $this->setEnvValue($original);
        }
    }

    private function tempBaseWithEnv(string $contents): void
    {
        $this->tempBase = storage_path('app/observe_guard_' . uniqid());
        File::makeDirectory($this->tempBase, 0755, true);
        File::put($this->tempBase . '/.env', $contents);
        app()->setBasePath($this->tempBase);
    }

    public function test_unset_environment_value_leaves_the_observer_disabled(): void
    {
        $this->assertFalse($this->flagFromConfigFile(null));
    }

    public function test_only_the_word_true_enables_the_observer(): void
    {
        $this->assertTrue($this->flagFromConfigFile('true'));

        // Anything else fails closed, including values a human might reasonably expect to mean "on".
        foreach (['false', '0', '1', 'yes', 'on', 'enabled', ''] as $value) {
            $this->assertFalse($this->flagFromConfigFile($value), "Value '{$value}' must not enable the observer.");
        }
    }

    public function test_policy_requires_a_real_boolean_true(): void
    {
        config(['agent.observe_env.enabled' => true]);
        $this->assertTrue(AgentObserveEnvPolicy::enabled());

        foreach (['true', '1', 1, null, false] as $value) {
            config(['agent.observe_env.enabled' => $value]);
            $this->assertFalse(AgentObserveEnvPolicy::enabled(), 'Non-boolean or false value must disable.');
        }
    }

    public function test_disabling_continuous_scan_does_not_enable_or_disable_the_observer(): void
    {
        config(['agent.continuous_scan.enabled' => false, 'agent.observe_env.enabled' => true]);
        $this->assertTrue(AgentObserveEnvPolicy::enabled());

        config(['agent.continuous_scan.enabled' => true, 'agent.observe_env.enabled' => false]);
        $this->assertFalse(AgentObserveEnvPolicy::enabled());
    }

    public function test_handler_refuses_a_queued_task_without_reading_the_file_when_disabled(): void
    {
        config(['agent.observe_env.enabled' => false]);
        $this->tempBaseWithEnv("SYNTHETIC_TOKEN=FAKEtoken0000000000000000000000\n");

        $scanner = Mockery::mock(SecretScanner::class);
        $scanner->shouldNotReceive('scan');
        $queue = Mockery::mock(AgentQueueInterface::class);
        $queue->shouldNotReceive('enqueue');
        $agentService = Mockery::mock(AgentService::class);

        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        $handler->handle(['id' => 'queued-observe-1', 'payload' => []]);

        $this->assertFalse(Cache::has('agent_observe_env_hash_' . md5(realpath($this->tempBase . '/.env'))));
    }

    public function test_handler_scans_when_enabled(): void
    {
        config(['agent.observe_env.enabled' => true]);
        $this->tempBaseWithEnv("SYNTHETIC_TOKEN=FAKEtoken0000000000000000000000\n");

        $scanner = Mockery::mock(SecretScanner::class);
        $scanner->shouldReceive('scan')->once()->andReturn([]);
        $queue = Mockery::mock(AgentQueueInterface::class);
        $agentService = Mockery::mock(AgentService::class);
        $agentService->shouldReceive('getAgentId')->andReturn('agent-test');

        $handler = new AgentObserveEnvTaskHandler($scanner, $queue, $agentService);
        $handler->handle(['id' => 'observe-enabled-1', 'payload' => []]);

        $this->addToAssertionCount(1); // The mock expectation above is the assertion.
    }

    public function test_enqueue_writes_no_observe_task_when_disabled(): void
    {
        config(['agent.observe_env.enabled' => false]);

        $queue = Mockery::mock(AgentQueueInterface::class);
        $queue->shouldNotReceive('enqueue');

        $result = (new AgentScheduledTasks($queue))->enqueueAsLocalAdministrator(
            'trustnode-local-agent-1',
            'agent.observe_env',
            ['target' => base_path('.env')],
        );

        $this->assertNull($result);
    }
}
