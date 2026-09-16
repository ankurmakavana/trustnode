<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use LogicException;

class AgentService
{
    const S_STARTING = 'starting';
    const S_RUNNING = 'running';
    const S_STOPPING = 'stopping';
    const S_STOPPED = 'stopped';
    const S_UNHEALTHY = 'unhealthy';
    
    const C_STATE = 'trustnode_agent_state';
    const C_HEARTBEAT = 'trustnode_agent_heartbeat';

    protected $allowedTransitions = [
        self::S_STOPPED => [self::S_STARTING],
        self::S_STARTING => [self::S_RUNNING],
        self::S_RUNNING => [self::S_STOPPING, self::S_STARTING, self::S_STOPPED],
        self::S_STOPPING => [self::S_STOPPED],
    ];

    protected $config;
    protected $state;
    protected $instanceId;

    public function __construct()
    {
        $this->config = config('agent');
        $this->state = $this->loadState();
        $this->instanceId = (string) Str::uuid();
    }

    public function getState()
    {
        return $this->state['state'] ?? self::S_STOPPED;
    }

    public function setState($state)
    {
        $current = $this->state['state'] ?? self::S_STOPPED;

        if ($current !== $state) {
            $allowed = $this->allowedTransitions[$current] ?? [];

            if (!in_array($state, $allowed, true)) {
                throw new LogicException("Invalid transition from {$current} to {$state}");
            }
        }

        $this->state['state'] = $state;
        $this->persistState($this->state);
        return $this;
    }

    public function start()
    {
        if ($this->isRunning()) {
            if (!$this->isHeartbeatStale()) {
                Log::warning('Active Agent already running locally. Aborting start.');
                $currentState = $this->loadState();
                if (isset($currentState['instance_id']) && $currentState['instance_id'] !== $this->instanceId) {
                    $this->state['state'] = self::S_STOPPED;
                }
                return;
            }
        }
        
        Log::info('Attempting to start TrustNode Agent');
        $this->ensureAgentId();

        if (!$this->acquireOwnership()) {
            Log::warning('Active Agent already running or ownership race lost. Aborting start.');
            $this->state['state'] = self::S_STOPPED;
            return;
        }

        Log::info('TrustNode Agent ownership acquired, proceeding with startup');
        
        // Sync local state to what was persisted in acquireOwnership
        $this->state['state'] = self::S_STARTING;
        $this->state['instance_id'] = $this->instanceId;
        
        if (!$this->getStartedAt()) {
            $this->setStartedAt(now());
        }
        
        $this->setState(self::S_RUNNING);
        $this->heartbeat();
        
        Log::info('TrustNode Agent started successfully', [
            'agent_id' => $this->getAgentId(),
            'state' => $this->getState()
        ]);
    }
    
    protected function acquireOwnership()
    {
        $driver = $this->config['state']['driver'];
        $table = $this->config['state']['table'];
        $agentId = $this->getAgentId();
        
        $targetState = array_merge($this->state, [
            'state' => self::S_STARTING,
            'instance_id' => $this->instanceId
        ]);
        
        if ($driver === 'database') {
            $record = DB::table($table)->where('agent_id', $agentId)->first();
            
            if (!$record) {
                try {
                    $inserted = DB::table($table)->insert([
                        'agent_id' => $agentId,
                        'state' => json_encode($targetState),
                        'updated_at' => now()
                    ]);
                    if ($inserted) {
                        return true;
                    }
                } catch (\Exception $e) {
                    $record = DB::table($table)->where('agent_id', $agentId)->first();
                }
            }
            
            if ($record) {
                $currentState = json_decode($record->state, true) ?? [];
                $status = $currentState['state'] ?? self::S_STOPPED;
                
                if ($status !== self::S_STOPPED && !$this->isHeartbeatStale()) {
                    return false;
                }
                
                // Compare-and-Swap (CAS) ensures atomicity
                $affected = DB::table($table)
                    ->where('agent_id', $agentId)
                    ->where('state', $record->state)
                    ->update([
                        'state' => json_encode($targetState),
                        'updated_at' => now()
                    ]);
                    
                return $affected > 0;
            }
        } elseif ($driver === 'cache') {
            $lockKey = "trustnode_agent_lock_{$agentId}";
            if (Cache::add($lockKey, $this->instanceId, 10)) {
                $cached = Cache::get($this->getStateCacheKey($agentId));
                $status = $cached['state'] ?? self::S_STOPPED;
                
                if ($status !== self::S_STOPPED && !$this->isHeartbeatStale()) {
                    Cache::forget($lockKey);
                    return false;
                }
                
                Cache::forever($this->getStateCacheKey($agentId), $targetState);
                Cache::forget($lockKey);
                return true;
            }
        }
        
        return false;
    }

    public function drain()
    {
        if ($this->isRunning()) {
            Log::info('Agent draining initiated.');
            $this->setState(self::S_STOPPING);
        }
    }

    public function stop()
    {
        if ($this->isStopped()) {
            Log::warning('Agent already stopped, skipping stop');
            return;
        }

        if ($this->isStopping()) {
            Log::warning('Agent already stopping, skipping stop');
            return;
        }

        Log::info('Stopping TrustNode Agent');
        $this->setState(self::S_STOPPING);
        $this->setState(self::S_STOPPED);
        $this->setStoppedAt(now());
        
        Log::info('TrustNode Agent stopped successfully', [
            'agent_id' => $this->getAgentId(),
            'state' => $this->getState(),
            'stopped_at' => $this->getStoppedAt()
        ]);
    }
    
    public function heartbeat()
    {
        if (!$this->isRunning()) {
            return;
        }
        
        $currentState = $this->loadState();
        if (isset($currentState['instance_id']) && $currentState['instance_id'] !== $this->instanceId) {
            Log::warning('Agent instance usurped by another runtime. Stopping heartbeat.', [
                'agent_id' => $this->getAgentId(),
                'this_instance' => $this->instanceId,
                'active_instance' => $currentState['instance_id']
            ]);
            $this->setState(self::S_STOPPED);
            return;
        }
        
        Cache::put(
            $this->getHeartbeatCacheKey(),
            now()->timestamp,
            $this->config['heartbeat']['timeout']
        );
        
        $this->setLastHeartbeatAt(now());
    }

    public function isHeartbeatStale()
    {
        $lastHeartbeat = Cache::get($this->getHeartbeatCacheKey());
        
        if (!$lastHeartbeat) {
            return true;
        }
        
        $staleThreshold = now()->subSeconds($this->config['heartbeat']['timeout'])->timestamp;
        return $lastHeartbeat < $staleThreshold;
    }

    public function getHealthStatus()
    {
        $isStale = $this->isHeartbeatStale();
        
        if ($this->getState() === self::S_STARTING) {
            $healthState = self::S_STARTING;
        } elseif ($this->getState() === self::S_STOPPING) {
            $healthState = self::S_STOPPING;
        } elseif ($this->getState() === self::S_STOPPED) {
            $healthState = self::S_STOPPED;
        } elseif ($isStale) {
            $healthState = self::S_UNHEALTHY;
        } else {
            $healthState = self::S_RUNNING;
        }
        
        return [
            'agent_id' => $this->getAgentId(),
            'status' => $healthState,
            'version' => $this->getVersion(),
            'state' => $this->getState(),
            'started_at' => $this->getStartedAt(),
            'last_heartbeat_at' => $this->getLastHeartbeatAt(),
            'stopped_at' => $this->getStoppedAt(),
            'heartbeat_stale' => $isStale,
            'instance_id' => $this->instanceId,
            'timestamp' => now()->toISOString()
        ];
    }
// State persistence methods
    protected function loadState()
    {
        $driver = $this->config['state']['driver'];
        $table = $this->config['state']['table'];
        $agentId = $this->getAgentId();

        if ($driver === 'database') {
            $record = DB::table($table)
                ->where('agent_id', $agentId)
                ->first();

            if ($record && $record->state !== null) {
                $state = json_decode($record->state, true);
                if (is_array($state)) {
                    return $state;
                }
            }
        } elseif ($driver === 'cache') {
            $cached = Cache::get($this->getStateCacheKey($agentId));
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }
        }

        // Default to stopped state with null timestamps
        return [
            'state' => self::S_STOPPED,
            'started_at' => null,
            'last_heartbeat_at' => null,
            'stopped_at' => null,
        ];
    }

    protected function persistState($state)
    {
        $state['instance_id'] = $this->instanceId;

        $driver = $this->config['state']['driver'];
        $table = $this->config['state']['table'];
        $agentId = $this->getAgentId();

        if ($driver === 'database') {
            $record = DB::table($table)->where('agent_id', $agentId)->first();
            
            if ($record) {
                $currentState = json_decode($record->state, true) ?? [];
                if (isset($currentState['instance_id']) && $currentState['instance_id'] !== $this->instanceId && $currentState['instance_id'] !== null) {
                    Log::warning('Refusing to persist state: instance usurped by another runtime.', [
                        'our_id' => $this->instanceId,
                        'db_id' => $currentState['instance_id']
                    ]);
                    return;
                }
                
                DB::table($table)
                    ->where('agent_id', $agentId)
                    ->where('state', $record->state)
                    ->update([
                        'state' => json_encode($state),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table($table)->insert([
                    'agent_id' => $agentId,
                    'state' => json_encode($state),
                    'updated_at' => now(),
                ]);
            }
        } elseif ($driver === 'cache') {
            $lockKey = "trustnode_agent_persist_lock_{$agentId}";
            if (Cache::add($lockKey, $this->instanceId, 5)) {
                $cached = Cache::get($this->getStateCacheKey($agentId));
                if ($cached && isset($cached['instance_id']) && $cached['instance_id'] !== $this->instanceId && $cached['instance_id'] !== null) {
                    Log::warning('Refusing to persist state: instance usurped by another runtime (cache).');
                    Cache::forget($lockKey);
                    return;
                }
                Cache::forever($this->getStateCacheKey($agentId), $state);
                Cache::forget($lockKey);
            }
        }
    }

    protected function getStateCacheKey($agentId)
    {
        return "trustnode_agent_state_{$agentId}";
    }

    protected function getHeartbeatCacheKey()
    {
        return self::C_HEARTBEAT . '_' . $this->getAgentId();
    }
// Agent ID methods
    public function getAgentId()
    {
        if (!$this->config['id']) {
            // Generate ID based on hostname and random string if not set
            $hostname = gethostname();
            $random = Str::random(8);
            $this->config['id'] = "{$hostname}-{$random}";
        }

        return $this->config['id'];
    }

    public function getInstanceId()
    {
        return $this->instanceId;
    }

    protected function ensureAgentId()
    {
        // This method ensures the agent ID is set
        // The getAgentId() method already handles generation if needed
        $this->getAgentId();
    }
// Timestamp getter/setter methods
    public function getStartedAt()
    {
        return $this->state['started_at'] ?? null;
    }

    public function setStartedAt($timestamp)
    {
        $this->state['started_at'] = $timestamp;
        $this->persistState($this->state);
        return $this;
    }

    public function getLastHeartbeatAt()
    {
        return $this->state['last_heartbeat_at'] ?? null;
    }

    public function setLastHeartbeatAt($timestamp)
    {
        $this->state['last_heartbeat_at'] = $timestamp;
        $this->persistState($this->state);
        return $this;
    }

    public function getStoppedAt()
    {
        return $this->state['stopped_at'] ?? null;
    }

    public function setStoppedAt($timestamp)
    {
        $this->state['stopped_at'] = $timestamp;
        $this->persistState($this->state);
        return $this;
    }

    // Version method
    public function getVersion()
    {
        return $this->config['version'] ?? '1.0.0';
    }

    // Helper methods
    public function isRunning()
    {
        return $this->getState() === self::S_RUNNING;
    }

    public function isStarting()
    {
        return $this->getState() === self::S_STARTING;
    }

    public function isStopping()
    {
        return $this->getState() === self::S_STOPPING;
    }

    public function isStopped()
    {
        return $this->getState() === self::S_STOPPED;
    }

    public function isUnhealthy()
    {
        return $this->getState() === self::S_UNHEALTHY;
    }

    public function getQueue(): \App\Contracts\AgentQueueInterface
    {
        return app(\App\Contracts\AgentQueueInterface::class);
    }
}