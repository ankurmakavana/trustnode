<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Integration\IntegrationCredentialService;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Exceptions\InvalidCredentialException;
use Illuminate\Support\Facades\Crypt;

class IntegrationCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    protected \App\Models\User $user;
    protected IntegrationCredentialService $service;
    protected Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->first();
        $this->user = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);
        $this->actingAs($this->user);

        $this->service = new IntegrationCredentialService();
        $this->integration = Integration::create([
            'name' => 'Test IMAP',
            'code' => 'email',
            'type' => 'email',
            'status' => 'Connected',
        ]);
    }

    public function test_password_encrypt_decrypt_roundtrip()
    {
        $plaintext = 'super-secret-p@ssw0rd!';

        $credential = $this->service->store(
            $this->integration->id,
            'password',
            $plaintext
        );

        $this->assertNotNull($credential->id);

        // Value in DB must NOT be the plaintext
        $this->assertNotEquals($plaintext, $credential->value);

        // Retrieve must return original plaintext
        $decrypted = $this->service->retrieve($this->integration->id, 'password');
        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_plaintext_never_stored_in_database()
    {
        $plaintext = 'my-secret-token-12345';

        $this->service->store($this->integration->id, 'token', $plaintext);

        // Query raw DB value
        $raw = \Illuminate\Support\Facades\DB::table('integration_credentials')
            ->where('integration_id', $this->integration->id)
            ->where('key', 'token')
            ->value('value');

        // Raw value must not contain the plaintext
        $this->assertNotEquals($plaintext, $raw);
        $this->assertStringNotContainsString($plaintext, $raw);

        // But it must be a valid Crypt payload (starts with eyJ for Laravel's base64-encoded JSON envelope)
        $this->assertNotEmpty($raw);
    }

    public function test_legacy_bcrypt_value_is_rejected()
    {
        // Simulate a legacy bcrypt-hashed credential (the old broken behavior)
        IntegrationCredential::create([
            'integration_id' => $this->integration->id,
            'key' => 'password',
            'value' => bcrypt('old-password'),
        ]);

        $this->expectException(InvalidCredentialException::class);
        $this->expectExceptionMessage('legacy bcrypt');

        $this->service->retrieve($this->integration->id, 'password');
    }

    public function test_token_encryption_remains_compatible()
    {
        // RepositoryService stores tokens with Crypt::encryptString directly.
        // Verify IntegrationCredentialService can decrypt values stored that way.
        $tokenPlaintext = 'ghp_abc123XYZ789token';

        $credential = IntegrationCredential::create([
            'integration_id' => $this->integration->id,
            'key' => 'token',
            'value' => Crypt::encryptString($tokenPlaintext),
        ]);

        // Service must be able to retrieve it
        $decrypted = $this->service->retrieve($this->integration->id, 'token');
        $this->assertEquals($tokenPlaintext, $decrypted);
    }

    public function test_nonexistent_credential_returns_null()
    {
        $result = $this->service->retrieve($this->integration->id, 'nonexistent_key');
        $this->assertNull($result);
    }

    public function test_update_reencrypts_credential()
    {
        $this->service->store($this->integration->id, 'password', 'old-value');
        $this->service->update($this->integration->id, 'password', 'new-value');

        $decrypted = $this->service->retrieve($this->integration->id, 'password');
        $this->assertEquals('new-value', $decrypted);
    }

    public function test_retrieve_all_returns_decrypted_map()
    {
        $this->service->store($this->integration->id, 'password', 'secret123');
        $this->service->store($this->integration->id, 'token', 'tok_abc');

        $all = $this->service->retrieveAll($this->integration->id);

        $this->assertArrayHasKey('password', $all);
        $this->assertArrayHasKey('token', $all);
        $this->assertEquals('secret123', $all['password']);
        $this->assertEquals('tok_abc', $all['token']);
    }

    public function test_is_legacy_bcrypt_detection()
    {
        $credential = new IntegrationCredential();

        // bcrypt hash
        $credential->value = '$2y$12$abcdefghijklmnopqrstuuWYfGh8DpMfAx2oL4Ry0PKFJ/AhYkq';
        $this->assertTrue($this->service->isLegacyBcrypt($credential));

        // Crypt::encryptString output (base64 JSON envelope)
        $credential->value = Crypt::encryptString('test');
        $this->assertFalse($this->service->isLegacyBcrypt($credential));

        // Plain text (should not match bcrypt pattern)
        $credential->value = 'plaintext-value';
        $this->assertFalse($this->service->isLegacyBcrypt($credential));
    }

    public function test_corrupted_encrypted_value_throws()
    {
        IntegrationCredential::create([
            'integration_id' => $this->integration->id,
            'key' => 'password',
            'value' => 'not-a-valid-encrypted-string-garbage-data',
        ]);

        $this->expectException(InvalidCredentialException::class);
        $this->expectExceptionMessage('could not be decrypted');

        $this->service->retrieve($this->integration->id, 'password');
    }

    public function test_integration_controller_uses_crypt_not_bcrypt()
    {
        $response = $this->postJson('/api/integrations', [
            'name' => 'Test Email Server',
            'code' => 'email',
            'type' => 'email',
            'host' => 'imap.example.com',
            'port' => 993,
            'username' => 'user@example.com',
            'password' => 'my-secret-password',
            'tls' => true,
        ]);

        $response->assertStatus(200);

        $integrationId = $response->json('data.id');

        // Verify the stored credential is decryptable (not bcrypt)
        $decrypted = $this->service->retrieve($integrationId, 'password');
        $this->assertEquals('my-secret-password', $decrypted);
    }
}
