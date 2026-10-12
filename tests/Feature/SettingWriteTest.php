<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SettingWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $adminRole = Role::where('slug', 'administrator')->first();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $org = Organization::factory()->create();
        Team::factory()->create(['organization_id' => $org->id])->users()->attach($this->admin->id);
    }

    public function test_unauthenticated_write_updates_a_key_already_stored_for_an_organization()
    {
        // Key is globally unique. A row saved by an admin carries that admin's organization.
        $this->actingAs($this->admin);
        Setting::setValue('mail_host', 'smtp.first.example');

        Auth::logout();

        // Runtime writes have no user. Before the fix, the scoped lookup could not see the existing row
        // and the insert violated the unique key on `key`.
        Setting::setValue('mail_host', 'smtp.second.example');

        $this->assertSame(1, Setting::withoutGlobalScopes()->where('key', 'mail_host')->count());
        $this->assertSame('smtp.second.example', Setting::globalValues(['mail_host'])['mail_host']);
    }

    public function test_unauthenticated_write_of_a_new_key_is_stored_without_an_organization()
    {
        Setting::setValue('mail_from_name', 'TrustNode');

        $setting = Setting::withoutGlobalScopes()->where('key', 'mail_from_name')->first();
        $this->assertNotNull($setting);
        $this->assertNull($setting->organization_id);
        $this->assertSame('TrustNode', Setting::globalValues(['mail_from_name'])['mail_from_name']);
    }

    public function test_unauthenticated_write_keeps_the_organization_that_already_owns_the_key()
    {
        $this->actingAs($this->admin);
        $setting = Setting::setValue('mail_from_address', 'alerts@first.example');
        $owner = $setting->organization_id;

        Auth::logout();
        Setting::setValue('mail_from_address', 'alerts@second.example');

        $this->assertSame($owner, Setting::withoutGlobalScopes()->where('key', 'mail_from_address')->value('organization_id'));
    }
}
