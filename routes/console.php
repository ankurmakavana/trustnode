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

Artisan::command('agent:run', function (AgentService $agent) {
    $this->info('TrustNode Agent is running.');
    while ($agent->isRunning()) {
        sleep(1);
    }
    $this->info('TrustNode Agent stopped.');
})->purpose('Run the TrustNode Agent process');
