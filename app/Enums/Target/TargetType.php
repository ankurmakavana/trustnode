<?php

namespace App\Enums\Target;

enum TargetType: string
{
    case DOMAIN = 'domain';
    case IP_ADDRESS = 'ip_address';
    case CIDR_RANGE = 'cidr_range';
    case URL = 'url';
    case API_ENDPOINT = 'api_endpoint';
    case MOBILE_APPLICATION = 'mobile_application';
    case CLOUD_RESOURCE = 'cloud_resource';

    /**
     * Get all values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
