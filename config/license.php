<?php

return [
    'enabled' => filter_var(env('TRUSTNODE_LICENSE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'url' => env('TRUSTNODE_LICENSE_URL', 'https://license.trustnode.io'),
    'timeout' => (int) env('TRUSTNODE_LICENSE_TIMEOUT', 5),
    'grace_hours' => (int) env('TRUSTNODE_LICENSE_GRACE_HOURS', 72),
];