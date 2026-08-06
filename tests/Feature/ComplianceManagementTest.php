<?php

namespace Tests\Feature;

use App\Models\ComplianceControl;
use App\Models\ComplianceFramework;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceManagementTest extends TestCase
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

    public function test_can_list_compliance_stats(): void
    {
        $framework = ComplianceFramework::create([
            'name' => 'OWASP Top 10 2021',
            'code' => 'OWASP',
            'description' => 'OWASP framework',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/compliance/stats');

        $response->assertStatus(200)
            ->assertJsonFragment(['code' => 'OWASP']);
    }

    public function test_can_show_framework_profile(): void
    {
        $framework = ComplianceFramework::create([
            'name' => 'OWASP Top 10 2021',
            'code' => 'OWASP',
            'description' => 'OWASP framework',
        ]);

        $control = ComplianceControl::create([
            'framework_id' => $framework->id,
            'code' => 'A01',
            'title' => 'Broken Access Control',
            'description' => 'Access control bypasses',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/compliance/OWASP');

        $response->assertStatus(200)
            ->assertJsonFragment(['code' => 'A01', 'title' => 'Broken Access Control']);
    }
}
