<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CORS must not reflect arbitrary origins. The API uses credentials (session cookies), so a wildcard
 * origin would let any website read authenticated responses. Only the local origins are allowed.
 */
class CorsPolicyTest extends TestCase
{
    public function test_a_foreign_origin_receives_no_allow_origin_header(): void
    {
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/system/status');

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_a_foreign_origin_preflight_is_not_allowed(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example',
            'Access-Control-Request-Method' => 'GET',
        ])->call('OPTIONS', '/api/system/status');

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_the_local_dev_server_origin_is_allowed(): void
    {
        $response = $this->withHeaders(['Origin' => 'http://localhost:5173'])->getJson('/api/system/status');

        $this->assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_the_wildcard_is_not_configured(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
    }
}
