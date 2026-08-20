<?php

namespace App\Services\License;

use App\Models\LicenseInstallation;
use Illuminate\Support\Facades\Log;

class LicenseManager
{
    public function __construct(
        protected LicenseApiClient $apiClient,
        protected InstallationIdentityService $identityService
    ) {}

    protected function getInstallation(): ?LicenseInstallation
    {
        return LicenseInstallation::first();
    }

    public function activate(string $licenseKey): array
    {
        $installationId = $this->identityService->getInstallationId();
        $fingerprint = $this->identityService->getFingerprint();
        $hostname = gethostname() ?: 'unknown';

        $response = $this->apiClient->activate($licenseKey, $installationId, $fingerprint, 'TrustNode Installation', $hostname);

        if ($response['success']) {
            $data = $response['data'];
            
            $installation = $this->getInstallation();
            if (!$installation) {
                // Failsafe in case IdentityService didn't create it
                $installation = LicenseInstallation::create(['installation_id' => $installationId]);
            }

            $installation->update([
                'installation_token' => $data['installation_token'],
                'license_status' => $data['details']['license']['status'] ?? 'active',
                'entitlements' => $data['details']['entitlements'] ?? [],
                'validated_at' => now(),
                'last_successful_validation_at' => now(),
                'grace_expires_at' => now()->addHours(config('license.grace_hours', 72)),
            ]);

            return ['success' => true, 'message' => 'License activated successfully.'];
        }

        return ['success' => false, 'error' => $response['error']];
    }

    public function validate(): array
    {
        $installation = $this->getInstallation();
        if (!$installation || !$installation->installation_token) {
            return ['success' => false, 'error' => 'No active license found.'];
        }

        $response = $this->apiClient->validate($installation->installation_token);

        if ($response['success']) {
            $data = $response['data'];
            $installation->update([
                'license_status' => $data['license']['status'] ?? 'active',
                'entitlements' => $data['entitlements'] ?? [],
                'validated_at' => now(),
                'last_successful_validation_at' => now(),
                'grace_expires_at' => now()->addHours(config('license.grace_hours', 72)),
            ]);
            return ['success' => true];
        }

        // Handle failure
        $error = $response['error']['code'] ?? 'UNKNOWN_ERROR';
        $status = $response['status'];

        if ($error === 'NETWORK_ERROR' || $status >= 500) {
            // Server unreachable, rely on grace period
            return ['success' => false, 'error' => 'License server unreachable. Operating in offline grace period.', 'grace' => true];
        }

        // Explicit rejection (401, 403, invalid token, revoked, suspended, expired)
        $installation->update([
            'license_status' => 'invalid',
            'entitlements' => [],
            'grace_expires_at' => now()->subMinute(), // Expire grace immediately
        ]);

        return ['success' => false, 'error' => 'License validation failed: ' . ($response['error']['message'] ?? 'Invalid license')];
    }

    public function deactivate(): array
    {
        $installation = $this->getInstallation();
        if (!$installation || !$installation->installation_token) {
            return ['success' => false, 'error' => 'No active license found.'];
        }

        $response = $this->apiClient->deactivate($installation->installation_token);

        // Even if network fails, we can clear locally to be safe, but ideally we wait for success.
        // For robustness, if they want to deactivate, we clear local state unconditionally.
        $installation->update([
            'installation_token' => null,
            'license_status' => 'deactivated',
            'entitlements' => [],
            'validated_at' => now(),
            'grace_expires_at' => now()->subMinute(),
        ]);

        return ['success' => true];
    }

    public function isProfessional(): bool
    {
        if (!config('license.enabled', true)) {
            return false;
        }

        $installation = $this->getInstallation();
        if (!$installation || !$installation->installation_token) {
            return false;
        }

        if ($installation->license_status !== 'active') {
            return false;
        }

        if ($installation->grace_expires_at && $installation->grace_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function status(): array
    {
        $installation = $this->getInstallation();
        if (!$installation || !$installation->installation_token) {
            return ['status' => 'community', 'message' => 'No license activated. Running in Community Mode.'];
        }

        if ($installation->license_status !== 'active') {
            return ['status' => 'invalid', 'message' => 'License is ' . $installation->license_status];
        }

        if ($installation->grace_expires_at && $installation->grace_expires_at->isPast()) {
            return ['status' => 'expired_grace', 'message' => 'License grace period expired. Running in Community Mode.'];
        }

        return ['status' => 'active', 'message' => 'TrustNode Professional License Active.'];
    }

    public function entitlements(): array
    {
        if (!$this->isProfessional()) {
            return [];
        }
        $installation = $this->getInstallation();
        return $installation ? ($installation->entitlements ?? []) : [];
    }

    public function allows(string $feature): bool
    {
        $entitlements = $this->entitlements();
        return isset($entitlements[$feature]) && $entitlements[$feature] === true;
    }
}
