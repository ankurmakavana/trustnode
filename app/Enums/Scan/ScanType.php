<?php

namespace App\Enums\Scan;

enum ScanType: string
{
    case WEB_APPLICATION = 'web_application';
    case NETWORK_IP = 'network_ip';
    case PORT_DISCOVERY = 'port_discovery';
    case API_VULNERABILITY = 'api_vulnerability';
    case CONTAINER_AUDIT = 'container_audit';
    case CLOUD_INFRASTRUCTURE = 'cloud_infrastructure';
    case REPOSITORY = 'repository';
    case DATABASE = 'database';
    case LOCAL = 'local';
}
