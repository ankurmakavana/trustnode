<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Capabilities\AgentScanCodebaseCapability;
use App\Services\Scan\RepositoryScanner;
use App\Contracts\AgentQueueInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class AgentScanCodebaseCapabilityTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mock mount dir
        if (!File::exists(storage_path('scan-mount-test'))) {
            File::makeDirectory(storage_path('scan-mount-test'), 0755, true);
        }
        Config::set('agent.scan_mount_dir', storage_path('scan-mount-test'));
        
        \App\Models\Role::firstOrCreate(['id' => 1], ['name' => 'Admin', 'slug' => 'admin']);
        $user = \App\Models\User::factory()->create(['role_id' => 1]);
        $org = \App\Models\Organization::factory()->create();
        $team = \App\Models\Team::factory()->create(['organization_id' => $org->id]);
        $team->users()->attach($user->id);
        \App\Services\TenantContext::setUserId($user->id);
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('scan-mount-test'))) {
            File::deleteDirectory(storage_path('scan-mount-test'));
        }
        parent::tearDown();
    }

    public function test_it_allows_scanning_inside_base_path()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $scanner->expects($this->once())->method('scan')->willReturn([]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $result = $capability->execute(['target_path' => app_path()], ['agent_id' => 'test-agent']);
        $this->assertIsArray($result);
    }

    public function test_it_allows_scanning_inside_scan_mount()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $scanner->expects($this->once())->method('scan')->willReturn([]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $result = $capability->execute(['target_path' => storage_path('scan-mount-test')], ['agent_id' => 'test-agent']);
        $this->assertIsArray($result);
    }

    public function test_it_rejects_paths_outside_boundaries()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or unauthorized target path');
        
        $capability->execute(['target_path' => '/tmp'], ['agent_id' => 'test-agent']);
    }

    public function test_it_rejects_non_existent_paths()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid target path: directory does not exist or cannot be resolved');
        
        $capability->execute(['target_path' => storage_path('scan-mount-test/does_not_exist_404')], ['agent_id' => 'test-agent']);
    }

    public function test_it_rejects_files()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        // create a file
        File::put(storage_path('scan-mount-test/test_file.php'), '<?php echo "test";');
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('directory does not exist or cannot be resolved');
        
        $capability->execute(['target_path' => storage_path('scan-mount-test/test_file.php')], ['agent_id' => 'test-agent']);
    }

    public function test_it_prevents_path_traversal()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or unauthorized target path');
        
        $capability->execute(['target_path' => storage_path('scan-mount-test/../../../../tmp')], ['agent_id' => 'test-agent']);
    }
    
    public function test_it_allows_scanning_via_valid_project_id()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        $scanner->expects($this->once())->method('scan')->willReturn([]);
        
        File::makeDirectory(storage_path('scan-mount-test/my-project'), 0755, true);
        
        $orgId = \App\Models\User::find(\App\Services\TenantContext::currentUserId())->organizations()->first()->id;
        
        $project = \App\Models\CodebaseProject::factory()->create([
            'organization_id' => $orgId,
            'path' => 'my-project',
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE,
            'created_by' => \App\Services\TenantContext::currentUserId(),
        ]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $result = $capability->execute(['project_id' => $project->id], ['agent_id' => 'test-agent']);
        $this->assertIsArray($result);
    }
    
    public function test_it_rejects_project_from_another_organization()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        File::makeDirectory(storage_path('scan-mount-test/other-org-project'), 0755, true);
        
        $project = \App\Models\CodebaseProject::factory()->create([
            'organization_id' => \App\Models\Organization::factory()->create()->id, // different org
            'path' => 'other-org-project',
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE,
            'created_by' => \App\Services\TenantContext::currentUserId(),
        ]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid, unauthorized, or inactive codebase project.');
        
        $capability->execute(['project_id' => $project->id], ['agent_id' => 'test-agent']);
    }
    
    public function test_it_rejects_archived_projects()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        File::makeDirectory(storage_path('scan-mount-test/archived-project'), 0755, true);
        
        $orgId = \App\Models\User::find(\App\Services\TenantContext::currentUserId())->organizations()->first()->id;
        
        $project = \App\Models\CodebaseProject::factory()->create([
            'organization_id' => $orgId,
            'path' => 'archived-project',
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ARCHIVED,
            'created_by' => \App\Services\TenantContext::currentUserId(),
        ]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid, unauthorized, or inactive codebase project.');
        
        $capability->execute(['project_id' => $project->id], ['agent_id' => 'test-agent']);
    }
    
    public function test_it_rejects_project_with_sibling_prefix_path()
    {
        $scanner = $this->createMock(RepositoryScanner::class);
        $queue = $this->createMock(AgentQueueInterface::class);
        
        // This is a sibling directory to scan-mount-test, e.g. scan-mount-test-secrets
        if (!File::exists(storage_path('scan-mount-test-secrets'))) {
            File::makeDirectory(storage_path('scan-mount-test-secrets'), 0755, true);
        }
        
        $orgId = \App\Models\User::find(\App\Services\TenantContext::currentUserId())->organizations()->first()->id;
        
        $project = \App\Models\CodebaseProject::factory()->create([
            'organization_id' => $orgId,
            'path' => '../scan-mount-test-secrets',
            'status' => \App\Enums\CodebaseProject\CodebaseProjectStatus::ACTIVE,
            'created_by' => \App\Services\TenantContext::currentUserId(),
        ]);
        
        $capability = new AgentScanCodebaseCapability($scanner, $queue);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or unauthorized target path. Target must be within the designated scan mount.');
        
        $capability->execute(['project_id' => $project->id], ['agent_id' => 'test-agent']);
        
        File::deleteDirectory(storage_path('scan-mount-test-secrets'));
    }
}
