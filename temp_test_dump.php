<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Plan;
use App\Models\License;
use App\Models\Release;
use App\Models\LicenseActivation;
use App\Services\InstallationTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReleaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $tokenService;
    protected $tokenData;
    protected $license;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tokenService = app(InstallationTokenService::class);
        $this->tokenData = $this->tokenService->generate();

        $user = User::create(['name' => 'Test User', 'email' => 'test_user@example.com', 'password' => bcrypt('password')]);
        $customer = Customer::create(['uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'name' => 'Test', 'email' => 'test@example.com']);
        $this->product = Product::create(['uuid' => (string) Str::uuid(), 'name' => 'TrustNode Core', 'slug' => 'trustnode-core']);
        $plan = Plan::create(['uuid' => (string) Str::uuid(), 'product_id' => $this->product->id, 'name' => 'Pro', 'slug' => 'pro', 'features' => []]);

        $this->license = License::create([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'product_id' => $this->product->id,
            'plan_id' => $plan->id,
            'license_key_hash' => hash('sha256', 'TESTKEY'),
            'license_key_lookup_hash' => hash('sha256', 'TESTKEY-LOOKUP'),
            'status' => 'ACTIVE',
        ]);

        LicenseActivation::create([
            'uuid' => (string) Str::uuid(),
            'license_id' => $this->license->id,
            'installation_id' => 'inst-1',
            'installation_fingerprint' => 'fp-1',
            'installation_token_hash' => $this->tokenData['hash'],
        ]);

        Storage::fake('local');
    }

    public function test_active_installation_can_request_authorized_release_metadata()
    {
        Release::create([
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'artifact_path' => 'releases/artifact.zip',
            'artifact_filename' => 'artifact.zip',
            'artifact_size' => 1024,
            'checksum' => 'fakehash',
            'is_active' => true,
            'is_latest' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenData['token']
        ])->getJson('/api/v1/releases/latest');

        $response->dump();

        $response->assertStatus(200);
        $response->assertJsonStructure(['version', 'checksum', 'artifact_size', 'download_url', 'expires_at']);
        $this->assertEquals('1.0.0', $response->json('version'));
    }

    public function test_inactive_license_cannot_access_release()
    {
        $this->license->update(['status' => 'REVOKED']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->tokenData['token']
        ])->getJson('/api/v1/releases/latest');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'LICENSE_REVOKED');
    }

    public function test_invalid_installation_token_cannot_access_release()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer INVALID'
        ])->getJson('/api/v1/releases/latest');

        $response->assertStatus(401);
    }

    public function test_expired_signed_url_fails()
    {
        $release = Release::create([
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'artifact_path' => 'releases/artifact.zip',
            'artifact_filename' => 'artifact.zip',
            'artifact_size' => 1024,
            'checksum' => 'fakehash',
            'is_active' => true,
            'is_latest' => true,
        ]);

        Storage::disk('local')->put('releases/artifact.zip', 'content');

        $url = URL::temporarySignedRoute(
            'api.v1.releases.download',
            now()->subMinutes(5),
            ['release' => $release->id]
        );

        $response = $this->get($url);
        $response->assertStatus(403);
    }

    public function test_valid_signed_url_downloads_artifact()
    {
        $release = Release::create([
            'product_id' => $this->product->id,
            'version' => '1.0.0',
            'artifact_path' => 'releases/artifact.zip',
            'artifact_filename' => 'artifact.zip',
            'artifact_size' => 1024,
            'checksum' => 'fakehash',
            'is_active' => true,
            'is_latest' => true,
        ]);

        Storage::disk('local')->put('releases/artifact.zip', 'content');

        $url = URL::temporarySignedRoute(
            'api.v1.releases.download',
            now()->addMinutes(15),
            ['release' => $release->id]
        );

        $response = $this->get($url);
        $response->assertStatus(200);
        $response->assertDownload('artifact.zip');
    }
}
