<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Bounded wait for agent ownership. A start refused because the previous owner's lease is still fresh is
 * retried every few seconds. The wait ends at the lease TTL plus one heartbeat interval. After that the
 * caller exits with a failure status, so a live owner is never displaced and the loop is never unbounded.
 */
class AgentStartRetry
{
    public function __construct(
        private int $leaseSeconds = 90,
        private int $heartbeatSeconds = 30,
        private int $retrySeconds = 5,
    ) {
    }

    /** Longest wait before giving up, in seconds. */
    public function deadlineSeconds(): int
    {
        return $this->leaseSeconds + $this->heartbeatSeconds;
    }

    /**
     * Tries to start the agent until this runtime owns it or the deadline passes.
     *
     * @param callable|null $sleep receives the number of seconds to wait. Defaults to sleep().
     * @return bool true when this runtime owns the agent
     */
    public function attempt(AgentService $agent, ?callable $sleep = null): bool
    {
        $sleep ??= static fn (int $seconds) => sleep($seconds);
        $startedAt = CarbonImmutable::now();

        while (true) {
            $agent->start();

            if ($agent->isRunning() || $agent->isStarting()) {
                return true;
            }

            $elapsed = CarbonImmutable::now()->getTimestamp() - $startedAt->getTimestamp();
            if ($elapsed >= $this->deadlineSeconds()) {
                return false;
            }

            $sleep($this->retrySeconds);
        }
    }
}
