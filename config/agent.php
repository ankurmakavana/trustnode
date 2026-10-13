<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TrustNode Agent Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the TrustNode Agent lifecycle foundation.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Agent Identity
    |--------------------------------------------------------------------------
    |
    | Unique identifier for this agent instance.
    | In production, this should be generated during registration.
    | For foundation task, we'll use a combination of hostname and random string.
    |
    */
    'id' => env('AGENT_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Agent Version
    |--------------------------------------------------------------------------
    |
    | Current version of the agent.
    |
    */
    'version' => env('AGENT_VERSION', '1.0.0-foundation'),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the agent heartbeat mechanism.
    |
    */
    'heartbeat' => [

        /*
        | Interval in seconds between heartbeats.
        */
        'interval' => 30,

        /*
        | Timeout in seconds before considering heartbeat stale.
        */
        'timeout' => 90,

    ],

    /*
    |--------------------------------------------------------------------------
    | State Persistence
    |--------------------------------------------------------------------------
    |
    | Configuration for persisting agent state.
    |
    */
    'state' => [

        /*
        | Driver for state persistence: 'database' or 'cache'
        */
        'driver' => 'database',

        /*
        | Table name for database driver
        */
        'table' => 'agent_states',

    ],

    /*
    |--------------------------------------------------------------------------
    | Local Queue Foundation
    |--------------------------------------------------------------------------
    |
    | Configuration for local event queue when central server is unavailable.
    |
    */
    'queue' => [

        /*
        | Driver for queue: 'database', 'redis', or 'sync'
        */
        'driver' => env('AGENT_QUEUE_DRIVER', 'database'),

        /*
        | Maximum queue size to prevent unbounded growth
        */
        'max_size' => 1000,

        /*
        | Retry configuration for failed event delivery
        */
        'retry' => [

            /*
            | Base delay in seconds for retry backoff
            */
            'base_delay' => 5,

            /*
            | Maximum number of retry attempts
            */
            'max_attempts' => 5,

            /*
            | Multiplier for exponential backoff
            */
            'multiplier' => 2,

        ],

        /*
        | Graceful drain timeout in seconds before forceful exit
        */
        'drain_timeout' => 15,

    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Guardrails
    |--------------------------------------------------------------------------
    |
    | Foundation for resource limits to prevent uncontrolled consumption.
    |
    */
    'guardrails' => [

        /*
        | Maximum memory usage in MB (soft limit)
        */
        'memory_mb' => 256,

        /*
        | Maximum execution time for agent operations in seconds
        */
        'max_execution_time' => 300,

    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Mount Directory
    |--------------------------------------------------------------------------
    |
    | The designated host directory mounted into the agent for scanning.
    | This acts as the secure boundary for codebase scanning outside TrustNode.
    |
    */
    'scan_mount_dir' => env('SCAN_HOST_DIR', '/mnt/trustnode-scan'),

    /*
    |--------------------------------------------------------------------------
    | .env Observer (agent.observe_env)
    |--------------------------------------------------------------------------
    |
    | Hashes the project .env on a schedule. When the file changes, it creates a scan record and
    | reports the secrets it contains. Fail-closed: it runs only when AGENT_OBSERVE_ENV_ENABLED is
    | exactly the word true (any other value, including 1 or yes, leaves it off).
    | This is independent of continuous_scan. Disabling continuous scanning does NOT stop it.
    |
    */
    'observe_env' => [
        'enabled' => env('AGENT_OBSERVE_ENV_ENABLED', false) === true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Continuous Scanning
    |--------------------------------------------------------------------------
    |
    | Configuration for session-based repeated scanning of authorized folders.
    | When enabled, the scheduler will periodically enqueue a codebase scan
    | task for the configured scan mount directory while the agent is running.
    |
    */
    'continuous_scan' => [

        /*
        | Enable or disable periodic automatic scanning.
        | Set AGENT_CONTINUOUS_SCAN=false in .env to disable.
        */
        'enabled' => env('AGENT_CONTINUOUS_SCAN', true),

        /*
        | Interval in minutes between automatic scans.
        | Minimum: 5 minutes. Maximum: 1440 minutes (24 hours).
        */
        'interval_minutes' => max(5, min(1440, (int) env('AGENT_SCAN_INTERVAL', 30))),

    ],

];