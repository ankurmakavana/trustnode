<?php

namespace App\Contracts;

interface AgentPlannerInterface
{
    /**
     * Determine the next capability and arguments based on the objective and current state.
     *
     * @param string $objective
     * @param int $step
     * @param string|null $lastAction
     * @param mixed $lastResult
     * @return array|null Returns an array with 'capability_id' and 'arguments' if a plan is made, or null if complete/unknown.
     */
    public function plan(string $objective, int $step, ?string $lastAction = null, mixed $lastResult = null): ?array;
}
