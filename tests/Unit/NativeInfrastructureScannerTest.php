<?php

namespace Tests\Unit;

use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\DTOs\Import\NormalizedFinding;
use Tests\TestCase;

class NativeInfrastructureScannerTest extends TestCase
{
    public function test_scan_aggregates_findings_correctly()
    {
        $scanner = $this->getMockBuilder(NativeInfrastructureScanner::class)
            ->onlyMethods(['discoverPorts', 'checkTls', 'checkHttp'])
            ->getMock();

        $target = new \App\Services\Scan\Infrastructure\ValidatedTarget('example.com', '93.184.216.34', true);
        
        $scanner->expects($this->once())
            ->method('discoverPorts')
            ->with('93.184.216.34')
            ->willReturn([80, 443]);

        $tlsFinding = new NormalizedFinding([
            'scanner' => 'Test', 'scannerRuleId' => 'TLS', 'title' => 'TLS finding', 'severity' => 'low', 'category' => 'test', 'description' => 'test', 'remediation' => 'test', 'technicalDetails' => 'test', 'evidence' => 'test', 'url' => 'test', 'assetIdentifier' => 'test'
        ]);

        $scanner->expects($this->once())
            ->method('checkTls')
            ->with($target, 443)
            ->willReturn([$tlsFinding]);

        $httpFinding = new NormalizedFinding([
            'scanner' => 'Test', 'scannerRuleId' => 'HTTP', 'title' => 'HTTP finding', 'severity' => 'low', 'category' => 'test', 'description' => 'test', 'remediation' => 'test', 'technicalDetails' => 'test', 'evidence' => 'test', 'url' => 'test', 'assetIdentifier' => 'test'
        ]);

        $scanner->expects($this->once())
            ->method('checkHttp')
            ->with($target, 'https', 443)
            ->willReturn([$httpFinding]);

        $findings = $scanner->scan($target);

        $this->assertCount(3, $findings); // 1 port finding + 1 tls + 1 http
        $this->assertEquals('Open Ports Detected', $findings[0]->title);
        $this->assertEquals('INFRA-PORT-001', $findings[0]->scannerRuleId);
        
        $this->assertSame($tlsFinding, $findings[1]);
        $this->assertSame($httpFinding, $findings[2]);
    }
}
