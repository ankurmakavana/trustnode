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
    // Fail-closed: the .env observer is off unless agent.observe_env.enabled is true.
    if (! \App\Services\AgentObserveEnvPolicy::enabled()) {
        return;
    }

    $activeAgent = \Illuminate\Support\Facades\DB::table('agent_states')
        ->where('state->state', 'running')
        ->first();
        
    if ($activeAgent) {
        try {
            $queue = app(\App\Contracts\AgentQueueInterface::class);
            if (!$queue->hasTaskType($activeAgent->agent_id, 'agent.observe_env')) {
                // Owned by the local administrator; enqueues nothing if no administrator exists.
                app(\App\Services\AgentScheduledTasks::class)->enqueueAsLocalAdministrator(
                    $activeAgent->agent_id,
                    'agent.observe_env',
                    ['target' => base_path('.env')]
                );
            }
        } catch (\App\Exceptions\QueueFullException $e) {
            // Ignore if queue is full, bounded operation
        }
    }
})->everyMinute()->name('agent.observe_env');

// Periodic codebase scan — configurable interval, reuses existing scanner
if (config('agent.continuous_scan.enabled', true)) {
    $scanInterval = config('agent.continuous_scan.interval_minutes', 30);

    Schedule::call(function () {
        $scanMountDir = config('agent.scan_mount_dir', '/mnt/trustnode-scan');

        // Only proceed if the scan mount directory actually exists
        if (!is_dir($scanMountDir)) {
            \Illuminate\Support\Facades\Log::debug('agent.continuous_scan: Scan mount directory not found, skipping.', ['path' => $scanMountDir]);
            return;
        }

        // Only proceed if an agent is actively running
        $activeAgent = \Illuminate\Support\Facades\DB::table('agent_states')
            ->where('state->state', 'running')
            ->first();

        if (!$activeAgent) {
            return;
        }

        // One project-scoped scan per eligible project (registered, active, continuous, owner's
        // organization). The whole scan mount is never scanned; duplicates are skipped per project.
        app(\App\Services\AgentScheduledTasks::class)->enqueueProjectScans($activeAgent->agent_id);
    })->cron("*/{$scanInterval} * * * *")->name('agent.continuous_scan');
}

