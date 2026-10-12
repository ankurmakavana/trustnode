<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalSurfaceHardeningTest extends TestCase
{
    public function test_debug_db_route_is_not_registered()
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertFalse($uris->contains('debug-db'));
    }

    public function test_debug_db_does_not_return_database_or_report_data_publicly()
    {
        $this->getJson('/debug-db')
            ->assertStatus(404);

        $this->get('/debug-db')
            ->assertDontSee('db_connection')
            ->assertDontSee('report_options');
    }

    public function test_compose_web_port_is_published_only_on_localhost()
    {
        $compose = file_get_contents(base_path('compose.dev.yaml'));

        $this->assertStringContainsString('"127.0.0.1:8000:80"', $compose);
        $this->assertDoesNotMatchRegularExpression('/-\s*"8000:80"/', $compose);
    }

    public function test_compose_dev_server_port_is_published_only_on_localhost()
    {
        $compose = file_get_contents(base_path('compose.dev.yaml'));

        $this->assertStringContainsString('"127.0.0.1:5173:5173"', $compose);
        $this->assertDoesNotMatchRegularExpression('/-\s*"5173:5173"/', $compose);
    }

    public function test_compose_publishes_no_host_ports_other_than_the_loopback_ones()
    {
        $compose = file_get_contents(base_path('compose.dev.yaml'));

        // Quoted entries with the host:container form are port publishes; volumes are unquoted.
        preg_match_all('/^\s+-\s+"([^"]+:\d+)"/m', $compose, $matches);

        $this->assertEquals(
            ['127.0.0.1:8000:80', '127.0.0.1:5173:5173'],
            $matches[1]
        );
    }

    public function test_nginx_stays_on_the_internal_compose_network()
    {
        $compose = file_get_contents(base_path('compose.dev.yaml'));

        preg_match('/^  nginx:\n(.*?)(?=^  \S)/ms', $compose, $match);

        $this->assertNotEmpty($match, 'nginx service block not found.');
        $this->assertStringContainsString('trustnode-network', $match[1]);
    }
}
