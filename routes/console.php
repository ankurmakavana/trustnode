<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\AgentService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodic agent heartbeat — uses configured heartbeat interval
Schedule::call(function () {
    $agent = app(AgentService::class);
    $agent->heartbeat();
})->everyThirtySeconds()->name('agent.heartbeat');

// Periodic agent security observation
Schedule::call(function () {
    $agent = app(AgentService::class);
    if ($agent->isRunning()) {
        try {
            $queue = app(\App\Contracts\AgentQueueInterface::class);
            if (!$queue->hasTaskType($agent->getAgentId(), 'agent.observe_env')) {
                $queue->enqueue($agent->getAgentId(), 'agent.observe_env', [
                    'target' => base_path('.env')
                ]);
            }
        } catch (\App\Exceptions\QueueFullException $e) {
            // Ignore if queue is full, bounded operation
        }
    }
})->everyMinute()->name('agent.observe_env');

Artisan::command('agent:run', function (\App\Services\AgentService $agent, \App\Services\AgentWorker $worker) {
    // Apply memory guardrail (process-level enforcement)
    $memoryMb = config('agent.guardrails.memory_mb', 256);
    if (!is_numeric($memoryMb) || $memoryMb <= 0) {
        $memoryMb = 256; // Fallback to safe default to prevent unbounded memory
    }
    ini_set('memory_limit', $memoryMb . 'M');

    $this->info('TrustNode Agent is running.');
    
    // Ensure safe polling interval (minimum 1 second to prevent CPU busy loops)
    $pollInterval = max(1, (int) config('agent.guardrails.poll_interval', 1));

    while ($agent->isRunning()) {
        $processed = $worker->runOnce();
        if (!$processed) {
            sleep($pollInterval);
        }
    }
    $this->info('TrustNode Agent stopped.');
})->purpose('Run the TrustNode Agent process');
