<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ScanLocalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $role = Role::where('slug', 'administrator')->first();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function createZipFile(string $filename, callable $contents): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_zip_');
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $contents($zip);
        $zip->close();

        return new UploadedFile($tempPath, $filename, 'application/zip', null, true);
    }

    public function test_authenticated_user_can_upload_local_project_archive(): void
    {
        Queue::fake();

        $file = $this->createZipFile('my-project.zip', function ($zip) {
            $zip->addFromString('index.php', '<?php echo "test";');
        });

        $response = $this->actingAs($this->user)
            ->postJson('/api/scans/local', [
                'archive' => $file,
                'target' => 'my-project',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'target',
                'type',
                'status',
            ]);

        $this->assertDatabaseHas('scans', [
            'name' => 'Local Scan: my-project',
            'type' => 'local',
            'status' => 'queued',
        ]);

        Queue::assertPushed(\App\Jobs\ScanLocalJob::class);
    }

    public function test_upload_requires_authentication(): void
    {
        $file = $this->createZipFile('my-project.zip', function ($zip) {
            $zip->addFromString('index.php', '<?php echo "test";');
        });

        $response = $this->postJson('/api/scans/local', [
            'archive' => $file,
            'target' => 'my-project',
        ]);

        $response->assertStatus(401);
    }

    public function test_upload_validates_required_parameters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/scans/local', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['archive', 'target']);
    }

    public function test_upload_rejects_non_zip_files(): void
    {
        $fakeFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson('/api/scans/local', [
                'archive' => $fakeFile,
                'target' => 'my-project',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['archive']);
    }
}
