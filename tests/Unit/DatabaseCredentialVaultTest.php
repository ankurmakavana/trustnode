<?php

namespace Tests\Unit;

use App\Services\Scan\Database\DatabaseCredentialVault;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DatabaseCredentialVaultTest extends TestCase
{
    private DatabaseCredentialVault $vault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vault = new DatabaseCredentialVault();
    }

    public function test_credential_bundle_is_encrypted_before_cache_persistence()
    {
        Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) {
            // Value must not be a plain json string or array, but an encrypted string.
            // In Laravel, encrypted strings usually contain payload, iv, mac or base64.
            $this->assertIsString($value);
            $this->assertStringNotContainsString('mysecretpassword', $value);
            
            // Try to decrypt it to verify it was encrypted via Crypt facade
            $decrypted = Crypt::decryptString($value);
            $decoded = json_decode($decrypted, true);
            $this->assertEquals('mysecretpassword', $decoded['password']);
            
            return true;
        });

        $this->vault->store([
            'username' => 'admin',
            'password' => 'mysecretpassword',
        ]);
    }

    public function test_credential_token_is_random_and_unpredictable()
    {
        Cache::shouldReceive('put')->twice();

        $token1 = $this->vault->store(['user' => '1']);
        $token2 = $this->vault->store(['user' => '2']);

        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1)); // bin2hex of 32 bytes = 64 chars
    }

    public function test_credential_retrieval_consumes_the_token()
    {
        $creds = ['user' => 'test', 'password' => 'pass'];
        $token = $this->vault->store($creds);

        // Verify exists
        $this->assertTrue($this->vault->exists($token));

        // Retrieve should get the valid credentials
        $retrieved = $this->vault->retrieveAndConsume($token);
        $this->assertEquals($creds, $retrieved);

        // Token should now be consumed
        $this->assertFalse($this->vault->exists($token));
        
        // Second retrieval should fail and return null
        $this->assertNull($this->vault->retrieveAndConsume($token));
    }

    public function test_second_retrieval_fails()
    {
        $token = $this->vault->store(['user' => 'test']);
        
        // Retrieve and consume
        $this->vault->retrieveAndConsume($token);
        
        // Second retrieval must fail
        $this->assertNull($this->vault->retrieveAndConsume($token));
    }

    public function test_delete_token_removes_it()
    {
        $token = $this->vault->store(['user' => 'test']);
        $this->assertTrue($this->vault->exists($token));
        
        $this->vault->delete($token);
        
        $this->assertFalse($this->vault->exists($token));
    }
}
