<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentService
{
    const S_STARTING = 'starting';
    const S_RUNNING = 'running';
    const S_STOPPING = 'stopping';
    const S_STOPPED = 'stopped';
    const S_UNHEALTHY = 'unhealthy';
    
    const C_STATE = 'trustnode_agent_state';
    const C_HEARTBEAT = 'trustnode_agent_heartbeat';

    protected $config;
    protected $state;

    public function __construct()
    {
        $this->config = config('agent');
        $this->state = $this->loadState();
    }

    public function getState()
    {
        return $this->state['state'] ?? self::S_STOPPED;
    }

    public function setState($state)
    {
        $this->state['state'] = $state;
        $this->persistState($this->state);
        return $this;
    }

    public function start()
    {
        if ($this->isRunning()) {
            Log::warning('Agent already running, skipping start');
            return;
        }

        Log::info('Starting TrustNode Agent');
        $this->setState(self::S_STARTING);
        $this->ensureAgentId();
        
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

    public function stop()
    {
        if ($this->isStopped()) {
            Log::warning('Agent already stopped, skipping stop');
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
        
        Cache::put(
            self::C_HEARTBEAT,
            now()->timestamp,
            $this->config['heartbeat']['timeout']
        );
        
        $this->setLastHeartbeatAt(now());
    }

    public function isHeartbeatStale()
    {
        $lastHeartbeat = Cache::get(self::C_HEARTBEAT);
        
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
        $driver = $this->config['state']['driver'];
        $table = $this->config['state']['table'];
        $agentId = $this->getAgentId();

        if ($driver === 'database') {
            DB::table($table)->updateOrInsert(
                ['agent_id' => $agentId],
                [
                    'state' => json_encode($state),
                    'updated_at' => now(),
                ]
            );
        } elseif ($driver === 'cache') {
            Cache::forever(
                $this->getStateCacheKey($agentId),
                $state
            );
        }
    }

    protected function getStateCacheKey($agentId)
    {
        return "trustnode_agent_state_{$agentId}";
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
}