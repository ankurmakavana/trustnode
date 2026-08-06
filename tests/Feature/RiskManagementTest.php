<?php

namespace Tests\Feature;

use App\Models\Risk;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();
        $operatorRole = Role::where('slug', 'operator')->first();

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@trustnode.local',
        ]);

        $this->operator = User::factory()->create([
            'role_id' => $operatorRole->id,
        ]);
    }

    public function test_can_list_risks(): void
    {
        $risk = Risk::create([
            'title' => 'SQL Injection Exposure',
            'likelihood' => 'Possible',
            'impact' => 'Major',
            'risk_score' => 12,
            'risk_level' => 'High',
            'status' => 'Open',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/risks');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'SQL Injection Exposure']);
    }

    public function test_can_create_risk_via_api(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/risks', [
            'title' => 'XSS Risk on Login',
            'likelihood' => 'Possible',
            'impact' => 'Moderate',
            'status' => 'Open',
            'owner_id' => $this->admin->id,
            'findings' => [],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('risks', [
            'title' => 'XSS Risk on Login',
            'risk_level' => 'Medium',
            'risk_score' => 9,
        ]);
    }

    public function test_can_add_treatment_plan(): void
    {
        $risk = Risk::create([
            'title' => 'Exposed Admin Panels',
            'likelihood' => 'Possible',
            'impact' => 'Moderate',
            'risk_score' => 9,
            'risk_level' => 'Medium',
            'status' => 'Open',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/risks/{$risk->id}/treatment", [
            'treatment_type' => 'Mitigate',
            'description' => 'Setup network proxy filtering rules',
            'target_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('risk_treatments', [
            'risk_id' => $risk->id,
            'treatment_type' => 'Mitigate',
            'description' => 'Setup network proxy filtering rules',
        ]);
    }

    public function test_can_accept_risk(): void
    {
        $risk = Risk::create([
            'title' => 'Exposed Admin Panels',
            'likelihood' => 'Possible',
            'impact' => 'Moderate',
            'risk_score' => 9,
            'risk_level' => 'Medium',
            'status' => 'Open',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/risks/{$risk->id}/accept");

        $response->assertStatus(200);
        $this->assertDatabaseHas('risks', [
            'id' => $risk->id,
            'status' => 'Accepted',
            'accepted' => true,
            'accepted_by' => $this->admin->id,
        ]);
    }
}
