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
        // Register the AgentService as a singleton
        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService();
        });
        
        $this->app->bind(AgentQueueInterface::class, AgentQueue::class);
        $this->app->singleton(\App\Contracts\AgentTaskHandlerRegistryInterface::class, \App\Services\AgentTaskHandlerRegistry::class);
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
    
    /**
     * Handle shutdown signals for graceful termination.
     *
     * @param AgentService $agent
     * @return void
     */
    protected function handleShutdownSignals(AgentService $agent)
    {
        // Register shutdown function for clean exit
        register_shutdown_function(function () use ($agent) {
            if ($agent->isRunning()) {
                $agent->stop();
            }
        });
        
        // Handle SIGTERM and SIGINT if pcntl is available
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, function () use ($agent) {
                $agent->stop();
                exit(0);
            });
            
            pcntl_signal(SIGINT, function () use ($agent) {
                $agent->stop();
                exit(0);
            });
        }
    }
}