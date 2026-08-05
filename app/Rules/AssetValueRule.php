<?php

namespace App\Rules;

use App\Enums\Asset\AssetType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AssetValueRule implements ValidationRule
{
    protected ?AssetType $type;

    public function __construct($type)
    {
        if ($type instanceof AssetType) {
            $this->type = $type;
        } elseif (is_string($type)) {
            $this->type = AssetType::tryFrom($type);
        } else {
            $this->type = null;
        }
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->type) {
            $fail('The asset type is invalid, so value cannot be validated.');

            return;
        }

        $val = trim($value);

        switch ($this->type) {
            case AssetType::DOMAIN:
                // Needs to match standard domain format (e.g. corp.internal or trustnode.com)
                if (! preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/', $val)) {
                    $fail('The value must be a valid domain name.');
                }
                break;

            case AssetType::SUBDOMAIN:
                // Match subdomains (e.g. portal.internal or api.trustnode.com)
                if (! preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.[a-zA-Z]{2,63}$/', $val)) {
                    $fail('The value must be a valid subdomain.');
                }
                break;

            case AssetType::IPV4:
                if (! filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $fail('The value must be a valid IPv4 address.');
                }
                break;

            case AssetType::IPV6:
                if (! filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $fail('The value must be a valid IPv6 address.');
                }
                break;

            case AssetType::CIDR:
                // Match CIDR block (IPv4 or IPv6 with subnet notation, e.g. 10.0.0.0/24 or 2001:db8::/32)
                if (! $this->isValidCidr($val)) {
                    $fail('The value must be a valid CIDR subnet block.');
                }
                break;

            case AssetType::URL:
                if (! filter_var($val, FILTER_VALIDATE_URL)) {
                    $fail('The value must be a valid URL.');
                }
                break;

            case AssetType::API_ENDPOINT:
                // Must be a valid URL format for API endpoints
                if (! filter_var($val, FILTER_VALIDATE_URL)) {
                    $fail('The value must be a valid API endpoint URL.');
                }
                break;

            case AssetType::HOSTNAME:
                // Hostnames can be a single word (e.g. "gateway") or FQDN-like
                if (! preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', $val) &&
                    ! preg_match('/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,63}$/', $val)) {
                    $fail('The value must be a valid hostname.');
                }
                break;
        }
    }

    /**
     * Validate CIDR notation.
     */
    protected function isValidCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }

        $ip = $parts[0];
        $netmask = $parts[1];

        if (! is_numeric($netmask)) {
            return false;
        }

        $netmask = (int) $netmask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $netmask >= 0 && $netmask <= 32;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $netmask >= 0 && $netmask <= 128;
        }

        return false;
    }
}
