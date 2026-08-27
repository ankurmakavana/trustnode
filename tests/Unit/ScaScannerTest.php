<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Scan\Scanners\ScaScanner;
use App\Services\Scan\Dependencies\ComposerLockParser;
use App\Services\Scan\Vulnerability\OsvApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ScaScannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_parses_composer_lock_correctly()
    {
        $parser = new ComposerLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/composer_vulnerable.lock'));
        
        $deps = $parser->parse($content);
        
        $this->assertCount(3, $deps);
        $this->assertEquals('guzzlehttp/guzzle', $deps[0]['package']);
        $this->assertEquals('7.4.1', $deps[0]['version']);
        $this->assertEquals('laravel/framework', $deps[1]['package']);
        $this->assertEquals('phpunit/phpunit', $deps[2]['package']);
    }

    public function test_it_queries_osv_and_returns_findings()
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([
                'results' => [
                    [
                        'vulns' => [
                            [
                                'id' => 'GHSA-c3h2-8vjw-963g',
                                'summary' => 'Cross-site Scripting in guzzlehttp/guzzle',
                                'details' => 'Guzzle is a PHP HTTP client...',
                                'aliases' => ['CVE-2022-27776'],
                                'affected' => [
                                    [
                                        'package' => [
                                            'ecosystem' => 'Packagist',
                                            'name' => 'guzzlehttp/guzzle',
                                        ],
                                        'ranges' => [
                                            [
                                                'events' => [
                                                    ['introduced' => '0'],
                                                    ['fixed' => '7.4.3'],
                                                ],
                                            ],
                                        ],
                                    ]
                                ],
                            ]
                        ]
                    ],
                    [], // laravel
                    []  // phpunit
                ]
            ], 200)
        ]);

        $scanner = new ScaScanner(new ComposerLockParser(), new OsvApiClient());
        $content = file_get_contents(base_path('tests/Fixtures/SCA/composer_vulnerable.lock'));
        
        $findings = $scanner->scan($content, [], 'composer.lock', 'https://github.com/repo');
        
        $this->assertCount(1, $findings);
        $this->assertEquals('ScaScanner', $findings[0]->scanner);
        $this->assertEquals('SCA', $findings[0]->category);
        $this->assertEquals('SEC-SCA-VULN', $findings[0]->scannerRuleId);
        $this->assertEquals('GHSA-c3h2-8vjw-963g - Cross-site Scripting in guzzlehttp/guzzle', $findings[0]->title);
        $this->assertEquals('CVE-2022-27776', $findings[0]->cve);
        $this->assertEquals('composer.lock', $findings[0]->path);
        $this->assertEquals('guzzlehttp/guzzle@7.4.1', $findings[0]->parameter);
        
        $this->assertStringContainsString('Ecosystem: Packagist', $findings[0]->technicalDetails);
        $this->assertStringContainsString('Fixed Version: 7.4.3', $findings[0]->technicalDetails);
    }

    public function test_it_does_not_fail_scan_if_osv_is_down()
    {
        Http::fake([
            'api.osv.dev/v1/querybatch' => Http::response([], 500)
        ]);

        $scanner = new ScaScanner(new ComposerLockParser(), new OsvApiClient());
        $content = file_get_contents(base_path('tests/Fixtures/SCA/composer_vulnerable.lock'));
        
        $findings = $scanner->scan($content, [], 'composer.lock', 'https://github.com/repo');
        
        $this->assertCount(0, $findings);
    }
}
