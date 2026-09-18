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

