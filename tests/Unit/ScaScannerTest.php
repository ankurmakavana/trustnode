<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Scan\Scanners\ScaScanner;
use App\Services\Scan\Dependencies\ComposerLockParser;
use App\Services\Scan\Dependencies\NpmLockParser;
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

    public function test_it_parses_package_lock_v1_correctly()
    {
        $parser = new NpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/package-lock-v1.json'));

        $deps = $parser->parse($content);

        $this->assertCount(3, $deps);
        $this->assertEquals('lodash', $deps[0]['package']);
        $this->assertEquals('4.17.15', $deps[0]['version']);
        $this->assertEquals('@scope/package', $deps[1]['package']);
        $this->assertEquals('1.0.0', $deps[1]['version']);
        $this->assertEquals('nested-package', $deps[2]['package']);
        $this->assertEquals('2.0.0', $deps[2]['version']);
    }

    public function test_it_parses_package_lock_v2_correctly()
    {
        $parser = new NpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/package-lock-v2.json'));

        $deps = $parser->parse($content);

        $this->assertCount(2, $deps);
        $this->assertEquals('moment', $deps[0]['package']);
        $this->assertEquals('2.29.1', $deps[0]['version']);
        $this->assertEquals('@scope/utils', $deps[1]['package']);
        $this->assertEquals('1.5.0', $deps[1]['version']);
    }

    public function test_it_parses_package_lock_v3_correctly()
    {
        $parser = new NpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/package-lock-v3.json'));

        $deps = $parser->parse($content);

        $this->assertCount(2, $deps);
        $this->assertEquals('axios', $deps[0]['package']);
        $this->assertEquals('0.21.0', $deps[0]['version']);
        $this->assertEquals('follow-redirects', $deps[1]['package']);
        $this->assertEquals('1.13.0', $deps[1]['version']);
    }

    public function test_it_parses_yarn_v1_normal_dependency()
    {
        $parser = new \App\Services\Scan\Dependencies\YarnLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/yarn-v1.lock'));
        $deps = $parser->parse($content);

        $react = collect($deps)->firstWhere('package', 'react');
        $this->assertNotNull($react);
        $this->assertEquals('18.2.0', $react['version']);
    }

    public function test_it_parses_yarn_v1_scoped_dependency()
    {
        $parser = new \App\Services\Scan\Dependencies\YarnLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/yarn-v1.lock'));
        $deps = $parser->parse($content);

        $babel = collect($deps)->firstWhere('package', '@babel/code-frame');
        $this->assertNotNull($babel);
        $this->assertEquals('7.12.11', $babel['version']);
    }

    public function test_it_deduplicates_yarn_v1_multiple_descriptors()
    {
        $parser = new \App\Services\Scan\Dependencies\YarnLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/yarn-v1.lock'));
        $deps = $parser->parse($content);

        // "lodash@^4.17.0", "lodash@~4.17.0" should resolve to 1 entry
        $lodashDeps = collect($deps)->where('package', 'lodash');
        $this->assertCount(1, $lodashDeps);
        $this->assertEquals('4.17.21', $lodashDeps->first()['version']);
    }

    public function test_it_rejects_yarn_berry_format()
    {
        $parser = new \App\Services\Scan\Dependencies\YarnLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/yarn-berry.lock'));
        $deps = $parser->parse($content);
        $this->assertEmpty($deps);
    }

    public function test_it_parses_pnpm_v5_format()
    {
        $parser = new \App\Services\Scan\Dependencies\PnpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/pnpm-v5.yaml'));
        $deps = $parser->parse($content);

        $lodash = collect($deps)->firstWhere('package', 'lodash');
        $this->assertNotNull($lodash);
        $this->assertEquals('4.17.21', $lodash['version']);
    }

    public function test_it_parses_pnpm_v6_format()
    {
        $parser = new \App\Services\Scan\Dependencies\PnpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/pnpm-lock-supported.yaml'));
        $deps = $parser->parse($content);

        $lodash = collect($deps)->firstWhere('package', 'lodash');
        $this->assertNotNull($lodash);
        $this->assertEquals('4.17.21', $lodash['version']);
    }

    public function test_it_parses_pnpm_v9_format()
    {
        $parser = new \App\Services\Scan\Dependencies\PnpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/pnpm-v9.yaml'));
        $deps = $parser->parse($content);

        $lodash = collect($deps)->firstWhere('package', 'lodash');
        $this->assertNotNull($lodash);
        $this->assertEquals('4.17.21', $lodash['version']);
    }

    public function test_it_parses_pnpm_scoped_package()
    {
        $parser = new \App\Services\Scan\Dependencies\PnpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/pnpm-v9.yaml'));
        $deps = $parser->parse($content);

        $babel = collect($deps)->firstWhere('package', '@babel/core');
        $this->assertNotNull($babel);
        $this->assertEquals('7.12.3', $babel['version']);
    }

    public function test_it_normalizes_pnpm_peer_dependency_suffix()
    {
        $parser = new \App\Services\Scan\Dependencies\PnpmLockParser();
        $content = file_get_contents(base_path('tests/Fixtures/SCA/pnpm-v9.yaml'));
        $deps = $parser->parse($content);

        $vite = collect($deps)->firstWhere('package', 'vite');
        $this->assertNotNull($vite);
        $this->assertEquals('5.0.0', $vite['version']); // @types/node@20.0.0 should be stripped
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

        $scanner = new ScaScanner(
            new ComposerLockParser(),
            new NpmLockParser(),
            new \App\Services\Scan\Dependencies\YarnLockParser(),
            new \App\Services\Scan\Dependencies\PnpmLockParser(),
            new OsvApiClient()
        );
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

        $scanner = new ScaScanner(
            new ComposerLockParser(),
            new NpmLockParser(),
            new \App\Services\Scan\Dependencies\YarnLockParser(),
            new \App\Services\Scan\Dependencies\PnpmLockParser(),
            new OsvApiClient()
        );
        $content = file_get_contents(base_path('tests/Fixtures/SCA/composer_vulnerable.lock'));

        $findings = $scanner->scan($content, [], 'composer.lock', 'https://github.com/repo');

        $this->assertCount(0, $findings);
    }
}
