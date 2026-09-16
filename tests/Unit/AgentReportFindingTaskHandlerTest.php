<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Handlers\AgentReportFindingTaskHandler;
use App\Services\Finding\FindingService;
use App\Services\Import\FingerprintService;
use App\Models\FindingIdentity;
use App\Models\Finding;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AgentReportFindingTaskHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_supports_report_finding_task()
    {
        $findingService = $this->createMock(FindingService::class);
        $fingerprintService = $this->createMock(FingerprintService::class);
        
        $handler = new AgentReportFindingTaskHandler($findingService, $fingerprintService);
        
        $this->assertTrue($handler->supports('agent.report_finding'));
        $this->assertFalse($handler->supports('deepseek_harness_execution'));
    }

    public function test_handler_creates_finding_and_truncates_evidence()
    {
        // Use real FindingService and FingerprintService to test database creation
        $handler = app(AgentReportFindingTaskHandler::class);
        
        $longEvidence = str_repeat('A', 2500);

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->firstOrFail();
        $user = \App\Models\User::factory()->create([
            'role_id' => $adminRole->id,
            'status' => \App\Enums\UserStatus::ACTIVE,
        ]);

        $task = [
            'id' => 'task-1',
            'type' => 'agent.report_finding',
            'agent_id' => 'agent-123',
            'payload' => [
                'title' => 'Test Agent Finding',
                'severity' => 'high',
                'description' => 'A test finding from the agent.',
                'evidence' => $longEvidence,
                'created_by' => $user->id,
            ]
        ];

        $handler->handle($task);
        
        $finding = Finding::where('title', 'Test Agent Finding')->first();
        
        $this->assertNotNull($finding);
        $this->assertEquals('Test Agent Finding', $finding->title);
        $this->assertEquals(\App\Enums\Finding\FindingSeverity::HIGH, $finding->severity);
        $this->assertNotNull($finding->finding_identity_id);
        
        // Assert truncation
        $this->assertEquals(2015, mb_strlen($finding->evidence));
        $this->assertStringEndsWith('... [truncated]', $finding->evidence);
        
        // Ensure identity is created
        $identity = FindingIdentity::find($finding->finding_identity_id);
        $this->assertNotNull($identity);
        $this->assertEquals('AgentObservation', $finding->scanner);
    }
}
