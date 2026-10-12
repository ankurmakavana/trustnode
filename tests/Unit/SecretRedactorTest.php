<?php

namespace Tests\Unit;

use App\Services\SecretRedactor;
use PHPUnit\Framework\TestCase;

class SecretRedactorTest extends TestCase
{
    // Synthetic values only. None of these are real credentials.
    private const FAKE_AWS = 'AKIAFAKEFAKEFAKEFAKE';
    private const FAKE_GITHUB = 'ghp_FAKEfakeFAKEfakeFAKEfakeFAKEfake1234';
    private const FAKE_STRIPE = 'sk_live_FAKEfakeFAKE1234';
    private const FAKE_JWT = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJmYWtlIn0.FAKEsignatureFAKE';

    public function test_token_shapes_are_redacted()
    {
        foreach ([self::FAKE_AWS, self::FAKE_GITHUB, self::FAKE_STRIPE, self::FAKE_JWT] as $token) {
            $out = SecretRedactor::redact("value is {$token} here");

            $this->assertStringNotContainsString($token, $out);
            $this->assertStringContainsString(SecretRedactor::PLACEHOLDER, $out);
        }
    }

    public function test_private_key_blocks_are_redacted()
    {
        $pem = "-----BEGIN RSA PRIVATE KEY-----\nFAKEKEYMATERIAL\n-----END RSA PRIVATE KEY-----";

        $out = SecretRedactor::redact("key:\n{$pem}\nend");

        $this->assertStringNotContainsString('FAKEKEYMATERIAL', $out);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $out);
    }

    public function test_url_credentials_are_redacted_and_host_is_kept()
    {
        $out = SecretRedactor::redact('clone https://deploy:FAKEpass@git.example.test/repo.git');

        $this->assertStringNotContainsString('FAKEpass', $out);
        $this->assertStringNotContainsString('deploy:', $out);
        $this->assertStringContainsString('git.example.test/repo.git', $out);
    }

    public function test_bearer_tokens_are_redacted()
    {
        $out = SecretRedactor::redact('Authorization: Bearer FAKEbearerTOKEN123');

        $this->assertStringNotContainsString('FAKEbearerTOKEN123', $out);
        $this->assertStringContainsString('Bearer [REDACTED]', $out);
    }

    public function test_assignments_with_sensitive_keys_are_redacted_in_all_common_forms()
    {
        $cases = [
            'DB_PASSWORD=FAKEdbpass123',
            'password: FAKEdbpass123',
            'api_key = "FAKEdbpass123"',
            "client_secret='FAKEdbpass123'",
            '"token": "FAKEdbpass123"',
            'AWS_SECRET_ACCESS_KEY=FAKEdbpass123',
        ];

        foreach ($cases as $line) {
            $out = SecretRedactor::redact($line);

            $this->assertStringNotContainsString('FAKEdbpass123', $out, "Not redacted: {$line}");
        }
    }

    public function test_assignment_keeps_the_key_name_for_context()
    {
        $out = SecretRedactor::redact('DB_PASSWORD=FAKEdbpass123');

        $this->assertStringStartsWith('DB_PASSWORD=', $out);
    }

    public function test_ordinary_text_is_unchanged()
    {
        $text = 'Input is not sanitized before it reaches the query builder.';

        $this->assertSame($text, SecretRedactor::redact($text));
    }

    public function test_null_and_empty_inputs_are_returned_unchanged()
    {
        $this->assertNull(SecretRedactor::redact(null));
        $this->assertSame('', SecretRedactor::redact(''));
    }

    public function test_redaction_is_idempotent()
    {
        $once = SecretRedactor::redact('DB_PASSWORD=FAKEdbpass123 and ' . self::FAKE_AWS);

        $this->assertSame($once, SecretRedactor::redact($once));
    }

    public function test_session_identifiers_and_cookies_are_redacted()
    {
        $cases = [
            'session_id=FAKEsess000111',
            'SESSIONID: FAKEsess000111',
            'Cookie: sessionid=FAKEsess000111',
            'signature=FAKEsig000111',
            'webhook_secret = "FAKEhook000111"',
        ];

        foreach ($cases as $line) {
            $this->assertStringNotContainsString('FAKEsess000111', SecretRedactor::redact($line), $line);
            $this->assertStringNotContainsString('FAKEsig000111', SecretRedactor::redact($line), $line);
            $this->assertStringNotContainsString('FAKEhook000111', SecretRedactor::redact($line), $line);
        }
    }

    public function test_http_basic_credentials_in_the_authorization_header_are_redacted()
    {
        $out = SecretRedactor::redact('Authorization: Basic RkFLRXVzZXI6RkFLRXBhc3MxMjM=');

        $this->assertStringNotContainsString('RkFLRXVzZXI6RkFLRXBhc3MxMjM', $out);
        $this->assertStringContainsString('Basic ' . SecretRedactor::PLACEHOLDER, $out);
    }

    public function test_connection_string_password_is_redacted(): void
    {
        $out = SecretRedactor::redact('connection_string=Server=db.internal;Password=FAKEdbpass123;Database=app');

        $this->assertStringNotContainsString('FAKEdbpass123', $out);
        $this->assertStringContainsString('Database=app', $out, 'Non-sensitive parts stay readable.');
    }

    public function test_ordinary_prose_with_the_same_words_is_not_changed()
    {
        // False-positive guards. "author" must not match an auth key. "Basic" must not match without the
        // Authorization header. Words without an assignment are not key-value pairs.
        $samples = [
            'author=Jane Doe reviewed the change',
            'Basic authentication is required for the admin area',
            'The session id is missing from the request',
            'A cookie policy describes how sessions are stored',
        ];

        foreach ($samples as $text) {
            $this->assertSame($text, SecretRedactor::redact($text), "Unexpected change: {$text}");
        }
    }

    public function test_a_bare_high_entropy_string_without_a_key_or_known_format_is_not_detected()
    {
        // Documented limit, not a guarantee. Pattern-based redaction cannot identify an arbitrary secret
        // that has no key name and no recognised format. Such values need a different control: never
        // store or send them in the first place.
        $bare = 'qW8vZ2mK9pL4xR7tY1nB6cD3fG0hJ5kA';

        $this->assertSame("value: {$bare}", SecretRedactor::redact("value: {$bare}"));
    }
}
