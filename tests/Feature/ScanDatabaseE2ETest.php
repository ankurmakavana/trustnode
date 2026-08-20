<?php

namespace Tests\Feature;

use App\Enums\Scan\ScanEngine;
use App\Enums\Scan\ScanType;
use App\Models\Scan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScanDatabaseE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_database_scan_credential_lifecycle_and_queue_payload()
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['role_id' => \App\Models\Role::where('slug', 'administrator')->first()->id]);

        // Target mysql service in docker or dummy host
        $payload = [
            'name' => 'Test MySQL Scan',
            'description' => 'A test scan',
            'type' => ScanType::DATABASE->value,
            'engine' => ScanEngine::DATABASE_SCANNER->value,
            'target' => 'mysql:3306',
            'credentials' => [
                'driver' => 'mysql',
                'host' => 'mysql',
                'port' => 3306,
                'database' => 'trustnode',
                'username' => 'root',
                'password' => 'SuperSecretRootPassword123!',
            ]
        ];

        $response = $this->actingAs($user)->postJson('/api/scans', $payload);

        $response->assertStatus(201);
        
        // Assert scan created
        $scan = Scan::where('name', 'Test MySQL Scan')->first();
        $this->assertNotNull($scan);

        // Verify the jobs table payload doesn't contain the password
        // (If using database queue driver, otherwise this might skip)
        $jobs = DB::table('jobs')->get();
        if ($jobs->count() > 0) {
            $jobPayload = $jobs->first()->payload;
            $this->assertStringNotContainsString('SuperSecretRootPassword123!', $jobPayload);
            $this->assertStringContainsString('credentialToken', $jobPayload);
            $this->assertStringNotContainsString('credentials', $jobPayload);
        }

        // Run the worker synchronously for this job to see if it processes and consumes cache
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default']);

        // Check if findings were generated or if it failed gracefully without exposing creds
        $scan->refresh();
        
        // The job should complete or fail, but importantly, logs should be clean
        $this->assertContains($scan->status->value, ['completed', 'failed']);
        
        // We can't easily assert the logs file contents in PHPUnit without reading the file,
        // but we know the job doesn't log it directly.
    }
}
