<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AgentService;
use App\Contracts\AgentQueueInterface;
use App\Services\AgentQueue;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService(
                config('agent.id') ?? gethostname() . '-' . uniqid(),
                config('agent.version')
            );
        });
        
        $this->app->singleton(\App\Contracts\AgentQueueInterface::class, \App\Services\AgentQueue::class);
        $this->app->singleton(\App\Contracts\AgentTaskHandlerRegistryInterface::class, \App\Services\AgentTaskHandlerRegistry::class);
        $this->app->singleton(\App\Contracts\AgentSecurityBoundaryInterface::class, \App\Services\AgentSecurityBoundary::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Start the agent when the application boots (but not during testing)
        // ONLY start if running the designated agent command
        if ($this->app->runningInConsole() && !$this->app->environment('testing')) {
            $command = $_SERVER['argv'][1] ?? null;
            if ($command === 'agent:run') {
                $this->startAgent();
            }
        }
    }
    
    /**
     * Start the agent service.
     *
     * @return void
     */
    protected function startAgent()
    {
        $agent = $this->app->make(AgentService::class);
        
        // Start the agent
        $agent->start();
        
        // Handle shutdown signals for graceful termination
        $this->handleShutdownSignals($agent);
    }
    
    protected function handleShutdownSignals(AgentService $agent)
    {
        // Register shutdown function for clean exit
        register_shutdown_function(function () use ($agent) {
            if ($agent->isRunning()) {
                $agent->stop();
            } elseif ($agent->isStopping()) {
                $agent->setState(\App\Services\AgentService::S_STOPPED);
                $agent->setStoppedAt(now());
            }
        });
        
        // Handle SIGTERM and SIGINT if pcntl is available
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            
            $handler = function () use ($agent) {
                if ($agent->isRunning()) {
                    Log::info('Shutdown signal received. Initiating graceful drain.');
                    $agent->drain();
                    
                    $timeout = config('agent.queue.drain_timeout', 15);
                    if (function_exists('pcntl_alarm') && $timeout > 0) {
                        pcntl_alarm($timeout);
                    }
                }
            };
            
            pcntl_signal(SIGTERM, $handler);
            pcntl_signal(SIGINT, $handler);
            
            if (function_exists('pcntl_alarm')) {
                pcntl_signal(SIGALRM, function () {
                    Log::warning('Drain timeout exceeded. Exiting forcefully.');
                    exit(1);
                });
            }
        }
    }
}