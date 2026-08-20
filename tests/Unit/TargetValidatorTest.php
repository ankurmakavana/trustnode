<?php

namespace Tests\Unit;

use App\Services\Scan\Infrastructure\TargetValidator;
use App\Services\Scan\Infrastructure\ValidatedTarget;
use PHPUnit\Framework\TestCase;

class TargetValidatorTest extends TestCase
{
    protected TargetValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        // Since we can't easily mock gethostbyname built-in function without extension,
        // we will create a partial mock of TargetValidator if we need to mock DNS,
        // or just use real resolvable public domains for tests.
        // To make it robust and deterministic, let's subclass it.
        $this->validator = new class extends TargetValidator {
            public array $mockDns = [
                'example.com' => '93.184.216.34',
                'google.com' => '142.250.190.46',
                'internal.test' => '192.168.1.100',
                'loopback.test' => '127.0.0.1',
                'linklocal.test' => '169.254.169.254',
            ];

            protected function resolveDns(string $domain): string
            {
                return $this->mockDns[$domain] ?? $domain;
            }
        };
    }

    public function test_valid_ipv4()
    {
        $target = $this->validator->validate('8.8.8.8');
        $this->assertInstanceOf(ValidatedTarget::class, $target);
        $this->assertEquals('8.8.8.8', $target->original);
        $this->assertEquals('8.8.8.8', $target->ip);
        $this->assertFalse($target->isDomain);
    }

    public function test_valid_ipv6()
    {
        $target = $this->validator->validate('2001:4860:4860::8888');
        $this->assertEquals('2001:4860:4860::8888', $target->ip);
    }

    public function test_valid_domain()
    {
        $target = $this->validator->validate('example.com');
        $this->assertEquals('example.com', $target->original);
        $this->assertEquals('93.184.216.34', $target->ip);
        $this->assertTrue($target->isDomain);
    }

    public function test_strips_protocol_path_port_credentials()
    {
        $target = $this->validator->validate('https://user:pass@example.com:8443/path/to/page');
        $this->assertEquals('example.com', $target->original);
    }

    public function test_rejects_empty_target()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('');
    }

    public function test_rejects_invalid_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('invalid domain!com');
    }

    public function test_rejects_loopback_ip()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('127.0.0.1');
    }

    public function test_rejects_localhost_domain()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('localhost');
    }

    public function test_rejects_shell_injection()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('example.com; rm -rf /');
    }

    public function test_rejects_private_ipv4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('10.0.0.5');
    }

    public function test_rejects_private_ipv4_172()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('172.16.0.5');
    }

    public function test_rejects_private_ipv4_192()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('192.168.1.1');
    }

    public function test_rejects_link_local_ipv4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('169.254.169.254');
    }

    public function test_rejects_private_ipv6()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('fd00::1');
    }

    public function test_rejects_domain_resolving_to_private_ip()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('internal.test');
    }

    public function test_rejects_domain_resolving_to_loopback()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate('loopback.test');
    }
}
