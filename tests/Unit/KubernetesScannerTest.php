<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\Scan\Scanners\KubernetesScanner;

class KubernetesScannerTest extends TestCase
{
    private KubernetesScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new KubernetesScanner();
    }

    public function test_it_detects_insecure_configurations()
    {
        $content = file_get_contents(__DIR__ . '/../Fixtures/IaC/kubernetes-insecure.yaml');
        $lines = explode("\n", $content);
        
        $findings = $this->scanner->scan($content, $lines, 'kubernetes-insecure.yaml', 'http://repo');

        $this->assertCount(7, $findings);

        $ruleIds = array_map(fn($f) => $f->scannerRuleId, $findings);
        $this->assertContains('SEC-IAC-K8S-HOST-NETWORK', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-HOST-PID', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-PRIVILEGED', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-PRIVILEGE-ESCALATION', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-RUN-AS-ROOT', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-HOST-PATH', $ruleIds);
        $this->assertContains('SEC-IAC-K8S-PUBLIC-SERVICE', $ruleIds);

        // Verify correct category and scanner
        foreach ($findings as $finding) {
            $this->assertEquals('IAC', $finding->category);
            $this->assertEquals('KubernetesScanner', $finding->scanner);
            $this->assertNotEmpty($finding->evidence);
            $this->assertNotEmpty($finding->remediation);
            $this->assertEquals('kubernetes-insecure.yaml', $finding->path);
        }

        // Verify specific finding
        $privFinding = array_values(array_filter($findings, fn($f) => $f->scannerRuleId === 'SEC-IAC-K8S-PRIVILEGED'))[0];
        $this->assertEquals('CRITICAL', $privFinding->severity);
        $this->assertStringContainsString('Deployment', $privFinding->technicalDetails);
    }

    public function test_it_ignores_secure_configurations()
    {
        $content = file_get_contents(__DIR__ . '/../Fixtures/IaC/kubernetes-secure.yaml');
        $lines = explode("\n", $content);
        
        $findings = $this->scanner->scan($content, $lines, 'kubernetes-secure.yaml', 'http://repo');

        $this->assertCount(0, $findings);
    }

    public function test_it_ignores_configmaps_and_false_positives()
    {
        $content = file_get_contents(__DIR__ . '/../Fixtures/IaC/kubernetes-false-positive.yaml');
        $lines = explode("\n", $content);
        
        $findings = $this->scanner->scan($content, $lines, 'kubernetes-false-positive.yaml', 'http://repo');

        $this->assertCount(0, $findings);
    }

    public function test_it_ignores_non_yaml_files()
    {
        $content = "privileged: true\nhostNetwork: true";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'test.txt', 'http://repo');
        
        $this->assertCount(0, $findings);
    }

    public function test_it_ignores_docker_compose()
    {
        $content = "privileged: true\nhostNetwork: true";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'docker-compose.yml', 'http://repo');
        
        $this->assertCount(0, $findings);
    }
}
