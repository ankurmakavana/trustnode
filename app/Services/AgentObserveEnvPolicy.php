<?php

namespace App\Services;

/**
 * Whether the .env observer (agent.observe_env) may run.
 *
 * Fail-closed: only the boolean true enables it. Any other value, a missing key, or a string such as
 * "true" disables it. Checked at every point where the observer is scheduled, enqueued, or executed.
 */
class AgentObserveEnvPolicy
{
    public static function enabled(): bool
    {
        return config('agent.observe_env.enabled') === true;
    }
}
