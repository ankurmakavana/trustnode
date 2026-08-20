<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\License\InstallationIdentityService;
use App\Models\LicenseInstallation;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InstallationIdentityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_installation_produces_same_id_and_fingerprint()
    {
        $service = new InstallationIdentityService();
        $id1 = $service->getInstallationId();
        $fingerprint1 = $service->getFingerprint();
        
        $id2 = $service->getInstallationId();
        $fingerprint2 = $service->getFingerprint();
        
        $this->assertEquals($id1, $id2);
        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    public function test_fingerprint_does_not_change_if_app_key_changes()
    {
        $service = new InstallationIdentityService();
        $fingerprint1 = $service->getFingerprint();
        
        Config::set('app.key', 'base64:differentkey0987654=');
        $fingerprint2 = $service->getFingerprint();
        
        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    public function test_fingerprint_does_not_change_if_app_url_changes()
    {
        $service = new InstallationIdentityService();
        $fingerprint1 = $service->getFingerprint();
        
        Config::set('app.url', 'https://different.url');
        $fingerprint2 = $service->getFingerprint();
        
        $this->assertEquals($fingerprint1, $fingerprint2);
    }

    public function test_stored_secret_is_encrypted_at_rest()
    {
        $service = new InstallationIdentityService();
        $service->getFingerprint(); // Trigger creation
        
        $installation = LicenseInstallation::first();
        $this->assertNotEmpty($installation->installation_secret);
        
        // Assert raw DB value is encrypted
        $rawSecret = \DB::table('license_installations')->first()->installation_secret;
        
        $this->assertNotEquals($installation->installation_secret, $rawSecret);
        $this->assertStringNotContainsString($installation->installation_secret, $rawSecret);
    }

    public function test_fingerprint_is_not_raw_secret()
    {
        $service = new InstallationIdentityService();
        $fingerprint = $service->getFingerprint();
        $installation = LicenseInstallation::first();
        
        $this->assertNotEquals($fingerprint, $installation->installation_secret);
    }

    public function test_newly_created_installation_gets_different_identity()
    {
        $service = new InstallationIdentityService();
        $id1 = $service->getInstallationId();
        $fingerprint1 = $service->getFingerprint();
        
        LicenseInstallation::truncate();
        
        $id2 = $service->getInstallationId();
        $fingerprint2 = $service->getFingerprint();
        
        $this->assertNotEquals($id1, $id2);
        $this->assertNotEquals($fingerprint1, $fingerprint2);
    }
}