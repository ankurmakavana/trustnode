<?php

namespace Tests\Unit;

use App\Services\AgentService;
use App\Services\AgentStartRetry;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bounded start retry, with time controlled by Carbon. The sleep callable advances the test clock, so the
 * 90 s lease and the 120 s deadline are exercised without waiting.
 */
class AgentStartRetryTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $t0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['agent.state.driver' => 'database', 'agent.id' => 'retry-agent', 'agent.heartbeat.timeout' => 90]);
        $this->t0 = Carbon::parse('2026-10-12 12:00:00');
        Carbon::setTestNow($this->t0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function advance(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    public function test_deadline_is_the_lease_ttl_plus_one_heartbeat_interval(): void
    {
        $this->assertSame(120, (new AgentStartRetry(90, 30, 5))->deadlineSeconds());
    }

    public function test_a_free_lease_is_taken_on_the_first_attempt_without_waiting(): void
    {
        $sleeps = [];

        $owned = (new AgentStartRetry())->attempt(new AgentService(), function (int $s) use (&$sleeps) {
            $sleeps[] = $s;
        });

        $this->assertTrue($owned);
        $this->assertSame([], $sleeps, 'No wait when the lease is free.');
    }

    public function test_an_expired_lease_is_taken_over_within_the_deadline(): void
    {
        $owner = new AgentService();
        $owner->start();
        $this->assertTrue($owner->isRunning());

        // The owner never heartbeats again. The challenger waits and retries, and the lease expires at +90 s.
        $challenger = new AgentService();
        $sleeps = 0;
        $owned = (new AgentStartRetry(90, 30, 5))->attempt($challenger, function (int $s) use (&$sleeps) {
            $sleeps++;
            $this->advance($s);
        });

        $this->assertTrue($owned, 'The challenger must take over once the lease has expired.');
        $this->assertTrue($challenger->isRunning());
        $this->assertGreaterThan(0, $sleeps, 'The challenger must have waited for the lease.');
        $this->assertLessThanOrEqual(120, Carbon::now()->getTimestamp() - $this->t0->getTimestamp());
    }

    public function test_a_lease_that_stays_renewed_is_not_taken_and_the_wait_is_bounded(): void
    {
        $owner = new AgentService();
        $owner->start();

        $challenger = new AgentService();
        $sleeps = 0;
        $owned = (new AgentStartRetry(90, 30, 5))->attempt($challenger, function (int $s) use ($owner, &$sleeps) {
            $sleeps++;
            $this->advance($s);
            // The live owner heartbeats throughout, so its lease never expires.
            $owner->heartbeat();
        });

        $this->assertFalse($owned, 'A live owner must not be displaced.');
        $this->assertTrue($owner->isRunning());
        $this->assertLessThanOrEqual(120 / 5, $sleeps, 'The wait must stop at the deadline, not loop forever.');
        $this->assertGreaterThanOrEqual(120, Carbon::now()->getTimestamp() - $this->t0->getTimestamp());
    }
}
