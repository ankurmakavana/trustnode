<?php

namespace Tests\Feature;

use App\Jobs\ValidateImportJob;
use App\Models\ImportJob;
use App\Models\Role;
use App\Models\User;
use App\Services\Import\PreviewService;
use App\Services\Import\ValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImportResultsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $adminRole = Role::where('slug', 'administrator')->first();

        $this->user = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@trustnode.local',
        ]);
    }

    /** @test */
    public function it_can_validate_nmap_xml_format()
    {
        $service = new ValidationService;
        $validXml = '<nmaprun><host><address addr="192.168.1.5" addrtype="ipv4"/><ports><port portid="80"><state state="open"/></port></ports></host></nmaprun>';

        $res = $service->validate($validXml, 'nmap');
        $this->assertTrue($res['valid']);
        $this->assertEquals(1, $res['hosts_count']);
        $this->assertEquals(1, $res['findings_count']);
    }

    /** @test */
    public function it_can_generate_preview_for_scanned_elements()
    {
        $service = new PreviewService;
        $parsed = [
            'assets' => [
                ['value' => '10.0.0.5', 'name' => 'Host 1'],
            ],
            'findings' => [
                ['title' => 'Critical Bug', 'severity' => 'critical', 'asset_value' => '10.0.0.5'],
                ['title' => 'High Bug', 'severity' => 'high', 'asset_value' => '10.0.0.5'],
            ],
        ];

        $preview = $service->generatePreview($parsed);
        $this->assertEquals(1, $preview['assets_count']);
        $this->assertEquals(2, $preview['findings_count']);
        $this->assertEquals(1, $preview['severities']['critical']);
        $this->assertEquals(1, $preview['severities']['high']);
    }

    /** @test */
    public function it_can_upload_a_scan_file_and_dispatch_validation_job()
    {
        Queue::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->create('nmap_results.xml', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/imports/upload', [
                'file' => $file,
                'scanner' => 'nmap',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $job = ImportJob::first();
        $this->assertNotNull($job);
        $this->assertEquals('file', $job->source_type);
        $this->assertEquals('pending', $job->status);

        Queue::assertPushed(ValidateImportJob::class);
    }

    /** @test */
    public function it_can_fetch_import_jobs_list_and_details()
    {
        $job = ImportJob::create([
            'status' => 'completed',
            'progress' => 100,
            'source_type' => 'file',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/imports');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');

        $responseDetails = $this->actingAs($this->user)
            ->getJson("/api/imports/{$job->uuid}");

        $responseDetails->assertStatus(200);
        $responseDetails->assertJsonPath('data.uuid', $job->uuid);
    }

    /** @test */
    public function it_executes_entire_import_pipeline_and_normalizes_data()
    {
        config(['queue.default' => 'sync']);
        Storage::fake('local');
        $validXml = '<nmaprun><host><address addr="192.168.10.5" addrtype="ipv4"/><ports><port portid="443"><state state="open"/><service name="https"/></port></ports></host></nmaprun>';

        $job = ImportJob::create([
            'status' => 'pending',
            'progress' => 0,
            'source_type' => 'file',
            'created_by' => $this->user->id,
        ]);

        $filepath = 'imports/test_nmap.xml';
        Storage::put($filepath, $validXml);

        $job->file()->create([
            'uuid' => Str::uuid()->toString(),
            'filename' => 'test_nmap.xml',
            'filepath' => $filepath,
            'filesize' => strlen($validXml),
            'mime_type' => 'text/xml',
        ]);

        // Run validation job synchronously to trigger pipeline chain
        $validator = new ValidationService;
        (new ValidateImportJob($job, 'nmap'))->handle($validator);

        // Verify database results
        $this->assertDatabaseHas('assets', [
            'value' => '192.168.10.5',
            'import_job_id' => $job->id,
        ]);

        $this->assertDatabaseHas('findings', [
            'title' => 'Open Port 443 (https)',
            'import_job_id' => $job->id,
        ]);

        $this->assertDatabaseHas('import_histories', [
            'import_job_id' => $job->id,
            'scanner' => 'nmap',
            'status' => 'completed',
        ]);

        $this->assertEquals('completed', $job->fresh()->status);
        $this->assertEquals(100, $job->fresh()->progress);
    }
}
