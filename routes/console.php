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
    $activeAgent = \Illuminate\Support\Facades\DB::table('agent_states')
        ->where('state->state', 'running')
        ->first();
        
    if ($activeAgent) {
        try {
            $queue = app(\App\Contracts\AgentQueueInterface::class);
            if (!$queue->hasTaskType($activeAgent->agent_id, 'agent.observe_env')) {
                $queue->enqueue($activeAgent->agent_id, 'agent.observe_env', [
                    'target' => base_path('.env')
                ]);
            }
        } catch (\App\Exceptions\QueueFullException $e) {
            // Ignore if queue is full, bounded operation
        }
    }
})->everyMinute()->name('agent.observe_env');

