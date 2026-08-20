<?php

namespace App\Services\Scan\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class DatabaseCredentialVault
{
    private const CACHE_PREFIX = 'db_scan_creds_';
    
    // 5 minutes TTL should be sufficient for the HTTP request to queue the job and the worker to pick it up.
    // If the queue takes longer, the credentials correctly expire for security.
    private const TTL_SECONDS = 300; 

    /**
     * Store credentials in the vault and return a one-time secure token.
     * 
     * @param array $credentials The credential array (driver, host, port, username, password, etc)
     * @return string The one-time token to retrieve the credentials.
     */
    public function store(array $credentials): string
    {
        $token = $this->createCredentialToken();
        
        // We hash the token to create the cache key, so the raw token isn't the key.
        // This mitigates the risk if the cache keys are dumped by an admin.
        $key = $this->getCacheKey($token);
        
        $encrypted = Crypt::encryptString(json_encode($credentials));
        
        Cache::put($key, $encrypted, self::TTL_SECONDS);
        
        return $token;
    }

    /**
     * Retrieve the credentials and IMMEDIATELY delete them.
     * One-time consumption mechanism.
     * 
     * @param string $token The secure token
     * @return array|null The credentials, or null if missing/expired.
     */
    public function retrieveAndConsume(string $token): ?array
    {
        $key = $this->getCacheKey($token);
        
        // pull() retrieves and deletes the item in one operation.
        $encrypted = Cache::pull($key);
        
        if (!$encrypted) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($encrypted);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            // Decryption failure (key changed, tampered, etc.)
            return null;
        }
    }

    /**
     * Explicitly delete a token if it exists (e.g. aborting before job starts).
     */
    public function delete(string $token): void
    {
        $key = $this->getCacheKey($token);
        Cache::forget($key);
    }

    /**
     * Check if a token currently exists in the vault.
     */
    public function exists(string $token): bool
    {
        $key = $this->getCacheKey($token);
        return Cache::has($key);
    }

    /**
     * Generate a cryptographically secure, unpredictable random token.
     */
    private function createCredentialToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash the token to derive the cache key.
     */
    private function getCacheKey(string $token): string
    {
        return self::CACHE_PREFIX . hash('sha256', $token);
    }
}
