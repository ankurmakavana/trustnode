<?php

namespace App\Enums\Asset;

enum AssetType: string
{
    case DOMAIN = 'domain';
    case SUBDOMAIN = 'subdomain';
    case IPV4 = 'ipv4';
    case IPV6 = 'ipv6';
    case CIDR = 'cidr';
    case URL = 'url';
    case API_ENDPOINT = 'api_endpoint';
    case HOSTNAME = 'hostname';
    case CODE_REPOSITORY = 'code_repository';

    /**
     * Get all values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
