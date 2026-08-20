<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use App\Models\LicenseInstallation;
use App\Facades\LicenseGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LicenseClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_mode_by_default()
    {
        $this->assertFalse(LicenseGate::isProfessional());
        $this->assertFalse(LicenseGate::allows('scheduled_scans'));
        $status = LicenseGate::status();
        $this->assertEquals('community', $status['status']);
    }

    public function test_activation_success()
    {
        Http::fake([
            '*/api/v1/licenses/activate' => Http::response([
                'success' => true,
                'data' => [
                    'installation_token' => 'inst_123',
                    'details' => [
                        'license' => ['status' => 'active'],
                        'entitlements' => ['scheduled_scans' => true]
                    ]
                ]
            ], 200)
        ]);

        $result = LicenseGate::activate('TN-KEY-123');
        $this->assertTrue($result['success']);

        $installation = LicenseInstallation::first();
        $this->assertNotNull($installation);
        $this->assertEquals('inst_123', $installation->installation_token);
        $this->assertTrue(LicenseGate::allows('scheduled_scans'));
    }

    public function test_validation_success()
    {
        $installation = LicenseInstallation::create([
            'installation_id' => 'uuid',
            'installation_token' => 'inst_123',
            'license_status' => 'active',
            'entitlements' => ['scheduled_scans' => true],
            'validated_at' => now()->subDay(),
            'last_successful_validation_at' => now()->subDay(),
            'grace_expires_at' => now()->addDays(2),
        ]);

        Http::fake([
            '*/api/v1/licenses/validate' => Http::response([
                'success' => true,
                'data' => [
                    'license' => ['status' => 'active'],
                    'entitlements' => ['scheduled_scans' => true, 'new_feature' => true]
                ]
            ], 200)
        ]);

        $result = LicenseGate::validate();
        $this->assertTrue($result['success']);
        $this->assertTrue(LicenseGate::allows('new_feature'));
    }

    public function test_network_failure_uses_grace_period()
    {
        LicenseInstallation::create([
            'installation_id' => 'uuid',
            'installation_token' => 'inst_123',
            'license_status' => 'active',
            'entitlements' => ['scheduled_scans' => true],
            'grace_expires_at' => now()->addHours(72),
        ]);

        Http::fake([
            '*/api/v1/licenses/validate' => Http::response(null, 500)
        ]);

        $result = LicenseGate::validate();
        $this->assertFalse($result['success']);
        $this->assertTrue(LicenseGate::isProfessional());
        $this->assertTrue(LicenseGate::allows('scheduled_scans'));
    }

    public function test_grace_period_expiry()
    {
        LicenseInstallation::create([
            'installation_id' => 'uuid',
            'installation_token' => 'inst_123',
            'license_status' => 'active',
            'grace_expires_at' => now()->subHour(),
        ]);

        $this->assertFalse(LicenseGate::isProfessional());
        $status = LicenseGate::status();
        $this->assertEquals('expired_grace', $status['status']);
    }

    public function test_explicit_rejection_disables_professional()
    {
        LicenseInstallation::create([
            'installation_id' => 'uuid',
            'installation_token' => 'inst_123',
            'license_status' => 'active',
            'grace_expires_at' => now()->addHours(72),
        ]);

        Http::fake([
            '*/api/v1/licenses/validate' => Http::response([
                'success' => false,
                'error' => ['code' => 'LICENSE_REVOKED', 'message' => 'Revoked']
            ], 403)
        ]);

        $result = LicenseGate::validate();
        $this->assertFalse($result['success']);
        $this->assertFalse(LicenseGate::isProfessional());
        
        $installation = LicenseInstallation::first();
        $this->assertEquals('invalid', $installation->license_status);
        $this->assertTrue($installation->grace_expires_at->isPast());
    }
}
