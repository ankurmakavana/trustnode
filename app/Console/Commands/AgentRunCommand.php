<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AgentService;
use App\Services\AgentWorker;
use Carbon\Carbon;

class AgentRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agent:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start and run the TrustNode Security Agent daemon';

    /**
     * Execute the console command.
     */
    public function handle(AgentService $agentService, AgentWorker $worker)
    {
        $this->info('TrustNode Agent process started.');
        
        // Ensure AgentServiceProvider's boot logic actually started the agent
        // and we acquired ownership. If not, we should exit.
        if (!$agentService->isRunning() && !$agentService->isStarting()) {
            $this->error('Failed to acquire agent ownership or agent is not in RUNNING state. Exiting.');
            return 1;
        }

        $lastHeartbeat = Carbon::now();
        $heartbeatInterval = config('agent.heartbeat.interval', 30);

        while (true) {
            // Check if agent was requested to stop
            if ($agentService->isStopping() || $agentService->isStopped()) {
                $this->info('Agent stopping gracefully.');
                break;
            }

            // Perform heartbeat if interval has passed
            $diff = Carbon::now()->timestamp - $lastHeartbeat->timestamp;
            if ($diff >= $heartbeatInterval) {
                $agentService->heartbeat();
                $lastHeartbeat = Carbon::now();
                
                // Ensure ownership wasn't lost
                if ($agentService->isStopped()) {
                    $this->error('Agent lost ownership. Stopping.');
                    break;
                }
            }

            // Process one task from the queue
            $processed = $worker->runOnce();

            if (!$processed) {
                // Sleep when idle
                usleep(500000); // 0.5 seconds
            }

            // Dispatch signals (if async signals are enabled)
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        return 0;
    }
}
