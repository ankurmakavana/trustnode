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
        
        // A restart inside the previous owner's lease is refused. Wait for the lease to expire, within a
        // bounded deadline (lease TTL plus one heartbeat interval), instead of exiting at once. If the
        // deadline passes, exit with status 1 as before, so a live owner is never displaced.
        if (!$agentService->isRunning() && !$agentService->isStarting()) {
            $this->info('Waiting for the previous agent lease to expire (bounded).');
            $retry = new \App\Services\AgentStartRetry(
                (int) config('agent.heartbeat.timeout', 90),
                (int) config('agent.heartbeat.interval', 30),
            );
            $owned = $retry->attempt($agentService);

            if (!$owned) {
                $this->error('Failed to acquire agent ownership within the lease window. Exiting.');
                return 1;
            }
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
