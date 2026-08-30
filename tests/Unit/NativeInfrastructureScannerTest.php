<?php

namespace Tests\Unit;

use App\Services\Scan\Infrastructure\NativeInfrastructureScanner;
use App\Services\Scan\Infrastructure\ValidatedTarget;
use App\DTOs\Import\NormalizedFinding;
use App\Services\Import\FingerprintService;
use Tests\TestCase;
use ReflectionClass;

class NativeInfrastructureScannerTest extends TestCase
{
    public function test_scan_aggregates_findings_correctly()
    {
        $scanner = $this->getMockBuilder(NativeInfrastructureScanner::class)
            ->onlyMethods(['discoverPorts', 'checkTls', 'checkHttp'])
            ->getMock();

        $target = new ValidatedTarget('example.com', '93.184.216.34', true);
        
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

    public function test_fingerprint_behavior()
    {
        $service = new FingerprintService();
        
        $finding1 = clone $this->getMockFinding();
        $finding2 = clone $this->getMockFinding();
        
        $fingerprint1 = $service->generate($finding1, null);
        $fingerprint2 = $service->generate($finding2, null);
        
        // Same target + same finding => same fingerprint
        $this->assertEquals($fingerprint1, $fingerprint2);
        
        // Different target => different fingerprint
        $finding3 = new NormalizedFinding([
            'scanner' => 'NativeInfrastructureScanner',
            'scannerRuleId' => 'INFRA-PORT-001',
            'title' => 'Open Ports Detected',
            'severity' => 'info',
            'category' => 'Network',
            'description' => 'Test',
            'remediation' => 'Test',
            'technicalDetails' => 'Test',
            'evidence' => 'Test',
            'url' => 'example2.com',
            'assetIdentifier' => 'example2.com'
        ]);
        $fingerprint3 = $service->generate($finding3, null);
        
        $this->assertNotEquals($fingerprint1, $fingerprint3);
        
        // Different port finding => different fingerprint
        $finding4 = new NormalizedFinding([
            'scanner' => 'NativeInfrastructureScanner',
            'scannerRuleId' => 'INFRA-TLS-001',
            'title' => 'Expired TLS/SSL Certificate',
            'severity' => 'info',
            'category' => 'Network',
            'description' => 'Test',
            'remediation' => 'Test',
            'technicalDetails' => 'Test',
            'evidence' => 'Test',
            'url' => 'example.com',
            'assetIdentifier' => 'example.com'
        ]);
        $fingerprint4 = $service->generate($finding4, null);
        
        $this->assertNotEquals($fingerprint1, $fingerprint4);
    }

    protected function getMockFinding(): NormalizedFinding
    {
        return new NormalizedFinding([
            'scanner' => 'NativeInfrastructureScanner',
            'scannerRuleId' => 'INFRA-PORT-001',
            'title' => 'Open Ports Detected',
            'severity' => 'info',
            'category' => 'Network',
            'description' => 'Test',
            'remediation' => 'Test',
            'technicalDetails' => 'Test',
            'evidence' => 'Test',
            'url' => 'example.com',
            'assetIdentifier' => 'example.com'
        ]);
    }
}
