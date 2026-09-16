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
        'max_execution_time' => 30,

    ],

];