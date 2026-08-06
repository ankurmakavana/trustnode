<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@trustnode.local',
        ]);
    }

    public function test_can_list_reports(): void
    {
        Report::create([
            'title' => 'Q3 Corporate Compliance Assessment',
            'type' => 'Compliance Report',
            'status' => 'Generated',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Q3 Corporate Compliance Assessment']);
    }

    public function test_can_create_report_via_api(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/reports', [
            'title' => 'Executive Threat Posture Summary',
            'type' => 'Executive Summary',
            'options' => [],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reports', [
            'title' => 'Executive Threat Posture Summary',
            'type' => 'Executive Summary',
            'status' => 'Generated',
        ]);
    }

    public function test_can_duplicate_report(): void
    {
        $report = Report::create([
            'title' => 'Technical Scan Review',
            'type' => 'Technical Assessment',
            'status' => 'Generated',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/reports/{$report->id}/duplicate");

        $response->assertStatus(201);
        $this->assertDatabaseHas('reports', [
            'title' => 'Technical Scan Review (Copy)',
            'type' => 'Technical Assessment',
        ]);
    }

    public function test_can_archive_report(): void
    {
        $report = Report::create([
            'title' => 'Risk Assessment Q2',
            'type' => 'Risk Report',
            'status' => 'Generated',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/reports/{$report->id}/archive");

        $response->assertStatus(200);
        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'Archived',
        ]);
    }
}
