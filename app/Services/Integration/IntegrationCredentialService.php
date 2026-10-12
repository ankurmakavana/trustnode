<?php

namespace App\Services\Integration;

use App\Models\Integration;
use App\Models\IntegrationCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

/**
 * Centralized service for safe storage and retrieval of integration credentials.
 *
 * All credential values are stored using Laravel's authenticated symmetric
 * encryption (Crypt::encryptString). Legacy bcrypt-hashed values cannot be
 * decrypted and are rejected with a clear error.
 */
class IntegrationCredentialService
{
    /**
     * Store a credential value using authenticated symmetric encryption.
     *
     * @param int    $integrationId  The parent integration ID
     * @param string $key            Credential key (e.g. 'password', 'token', 'api_key')
     * @param string $plaintextValue The plaintext secret value
     * @return IntegrationCredential
     */
    public function store(int $integrationId, string $key, string $plaintextValue): IntegrationCredential
    {
        return IntegrationCredential::create([
            'integration_id' => $integrationId,
            'key' => $key,
            'value' => Crypt::encryptString($plaintextValue),
        ]);
    }

    /**
     * Retrieve and decrypt a credential value.
     *
     * Returns the plaintext value or null if the credential does not exist.
     * Throws InvalidCredentialException if the stored value cannot be decrypted
     * (e.g. legacy bcrypt hash, corrupted data, or key rotation).
     *
     * @param int    $integrationId The parent integration ID
     * @param string $key           Credential key to retrieve
     * @return string|null The decrypted plaintext value, or null if not found
     * @throws \App\Exceptions\InvalidCredentialException If decryption fails (legacy bcrypt, corruption)
     */
    public function retrieve(int $integrationId, string $key): ?string
    {
        $credential = IntegrationCredential::withoutGlobalScopes()
            ->where('integration_id', $integrationId)
            ->where('key', $key)
            ->first();

        if (!$credential) {
            return null;
        }

        return $this->decrypt($credential);
    }

    /**
     * Retrieve all credentials for an integration as key => plaintext pairs.
     *
     * @param int $integrationId
     * @return array<string, string>
     * @throws \App\Exceptions\InvalidCredentialException
     */
    public function retrieveAll(int $integrationId): array
    {
        $credentials = IntegrationCredential::withoutGlobalScopes()
            ->where('integration_id', $integrationId)
            ->get();

        $result = [];
        foreach ($credentials as $credential) {
            $result[$credential->key] = $this->decrypt($credential);
        }

        return $result;
    }

    /**
     * Update an existing credential's value (re-encrypts).
     *
     * @param int    $integrationId
     * @param string $key
     * @param string $plaintextValue
     * @return bool True if updated, false if credential not found
     */
    public function update(int $integrationId, string $key, string $plaintextValue): bool
    {
        $credential = IntegrationCredential::withoutGlobalScopes()
            ->where('integration_id', $integrationId)
            ->where('key', $key)
            ->first();

        if (!$credential) {
            return false;
        }

        $credential->value = Crypt::encryptString($plaintextValue);
        $credential->save();

        return true;
    }

    /**
     * Check whether a stored credential value is a legacy bcrypt hash
     * that cannot be decrypted.
     *
     * @param IntegrationCredential $credential
     * @return bool
     */
    public function isLegacyBcrypt(IntegrationCredential $credential): bool
    {
        // bcrypt hashes always start with $2y$, $2a$, or $2b$
        return preg_match('/^\$2[yab]\$/', $credential->value) === 1;
    }

    /**
     * Decrypt a single credential record.
     *
     * @param IntegrationCredential $credential
     * @return string
     * @throws \App\Exceptions\InvalidCredentialException
     */
    private function decrypt(IntegrationCredential $credential): string
    {
        if ($this->isLegacyBcrypt($credential)) {
            throw new \App\Exceptions\InvalidCredentialException(
                "Credential '{$credential->key}' for integration #{$credential->integration_id} " .
                "uses legacy bcrypt hashing and cannot be decrypted. The credential must be re-entered."
            );
        }

        try {
            return Crypt::decryptString($credential->value);
        } catch (DecryptException $e) {
            throw new \App\Exceptions\InvalidCredentialException(
                "Credential '{$credential->key}' for integration #{$credential->integration_id} " .
                "could not be decrypted. It may be corrupted or the encryption key may have changed. " .
                "The credential must be re-entered."
            );
        }
    }
}
