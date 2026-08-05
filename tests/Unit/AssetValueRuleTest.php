<?php

namespace Tests\Unit;

use App\Enums\Asset\AssetType;
use App\Rules\AssetValueRule;
use PHPUnit\Framework\TestCase;

class AssetValueRuleTest extends TestCase
{
    /**
     * @dataProvider assetValueProvider
     */
    public function test_validation_passes_for_valid_values(AssetType $type, string $value)
    {
        $rule = new AssetValueRule($type);
        $passed = true;

        $rule->validate('value', $value, function ($message) use (&$passed) {
            $passed = false;
        });

        $this->assertTrue($passed, "Failed asserting that '{$value}' is valid for type '{$type->value}'");
    }

    /**
     * @dataProvider assetValueInvalidProvider
     */
    public function test_validation_fails_for_invalid_values(AssetType $type, string $value)
    {
        $rule = new AssetValueRule($type);
        $failed = false;

        $rule->validate('value', $value, function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, "Failed asserting that '{$value}' is invalid for type '{$type->value}'");
    }

    public static function assetValueProvider(): array
    {
        return [
            [AssetType::DOMAIN, 'trustnode.com'],
            [AssetType::DOMAIN, 'portal.internal'],
            [AssetType::SUBDOMAIN, 'api.trustnode.com'],
            [AssetType::SUBDOMAIN, 'portal.corp.internal'],
            [AssetType::IPV4, '192.168.1.1'],
            [AssetType::IPV4, '10.0.0.1'],
            [AssetType::IPV6, '2001:db8::1'],
            [AssetType::IPV6, '::1'],
            [AssetType::CIDR, '10.10.0.0/24'],
            [AssetType::CIDR, '192.168.0.0/16'],
            [AssetType::CIDR, '2001:db8::/32'],
            [AssetType::URL, 'https://trustnode.com'],
            [AssetType::URL, 'http://127.0.0.1:8000/auth'],
            [AssetType::API_ENDPOINT, 'https://api.internal/v1/users'],
            [AssetType::API_ENDPOINT, 'http://localhost/api/v2'],
            [AssetType::HOSTNAME, 'gateway'],
            [AssetType::HOSTNAME, 'mail-server-01'],
            [AssetType::HOSTNAME, 'storage.internal'],
        ];
    }

    public static function assetValueInvalidProvider(): array
    {
        return [
            [AssetType::DOMAIN, 'not-a-domain'],
            [AssetType::DOMAIN, 'domain.c'],
            [AssetType::SUBDOMAIN, 'domain.com'], // needs at least a third-level label
            [AssetType::IPV4, '256.0.0.1'],
            [AssetType::IPV4, '10.0.0.300'],
            [AssetType::IPV4, 'not-an-ip'],
            [AssetType::IPV6, '2001:db8::g'],
            [AssetType::CIDR, '10.10.0.0'], // no subnet mask
            [AssetType::CIDR, '10.10.0.0/33'], // invalid IPv4 subnet mask
            [AssetType::CIDR, '2001:db8::/129'], // invalid IPv6 subnet mask
            [AssetType::URL, 'not-a-url'],
            [AssetType::URL, '://missing-scheme.com'],
            [AssetType::API_ENDPOINT, 'not-an-api'],
            [AssetType::HOSTNAME, 'inv@lid_name'],
        ];
    }
}
