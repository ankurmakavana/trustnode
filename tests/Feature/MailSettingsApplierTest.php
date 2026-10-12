<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use App\Services\Mail\MailSettingsApplier;
use App\Services\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Saved mail settings must reach the runtime mail configuration in a process with no user (a queue
 * worker or the scheduler). Values below are synthetic.
 */
class MailSettingsApplierTest extends TestCase
{
    use RefreshDatabase;

    private const SAVED_PASSWORD = 'synthetic-smtp-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        Auth::logout();
        TenantContext::clear();
        parent::tearDown();
    }

    private function saveSettingsAsAdmin(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'administrator')->firstOrFail()->id]);
        Team::factory()->create(['organization_id' => Organization::factory()->create()->id])->users()->attach($admin->id);
        $this->actingAs($admin);

        Setting::setValue('mail_mailer', 'smtp');
        Setting::setValue('mail_host', 'smtp.saved.example');
        Setting::setValue('mail_port', '2525');
        Setting::setValue('mail_username', 'saved-user');
        Setting::setValue('mail_password', self::SAVED_PASSWORD, true);
        Setting::setValue('mail_encryption', 'tls');
        Setting::setValue('mail_from_address', 'alerts@saved.example');
        Setting::setValue('mail_from_name', 'TrustNode Alerts');

        // The worker and scheduler run without a user.
        Auth::logout();
        TenantContext::clear();
    }

    private function setEnvDefaults(): void
    {
        Config::set('mail.default', 'log');
        Config::set('mail.mailers.smtp.host', 'env.default.example');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.from.address', 'env@default.example');
    }

    public function test_saved_settings_are_not_visible_to_a_userless_tenant_scoped_read(): void
    {
        // Documents why the installation-wide read exists.
        $this->saveSettingsAsAdmin();

        $this->assertNull(Setting::getValue('mail_host'));
    }

    public function test_saved_settings_reach_the_runtime_mail_config_without_a_user(): void
    {
        $this->saveSettingsAsAdmin();
        $this->setEnvDefaults();

        MailSettingsApplier::apply();

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('smtp.saved.example', Config::get('mail.mailers.smtp.host'));
        $this->assertSame('2525', (string) Config::get('mail.mailers.smtp.port'));
        $this->assertSame('saved-user', Config::get('mail.mailers.smtp.username'));
        $this->assertSame('tls', Config::get('mail.mailers.smtp.encryption'));
        $this->assertSame('alerts@saved.example', Config::get('mail.from.address'));
        $this->assertSame('TrustNode Alerts', Config::get('mail.from.name'));
    }

    public function test_saved_password_is_applied_decrypted(): void
    {
        $this->saveSettingsAsAdmin();

        MailSettingsApplier::apply();

        $this->assertTrue(
            Config::get('mail.mailers.smtp.password') === self::SAVED_PASSWORD,
            'The encrypted SMTP password must be applied in decrypted form.'
        );
    }

    public function test_env_defaults_are_kept_when_nothing_was_saved(): void
    {
        $this->setEnvDefaults();

        MailSettingsApplier::apply();

        $this->assertSame('log', Config::get('mail.default'));
        $this->assertSame('env.default.example', Config::get('mail.mailers.smtp.host'));
        $this->assertSame('env@default.example', Config::get('mail.from.address'));
    }

    public function test_boot_time_application_never_throws(): void
    {
        $this->saveSettingsAsAdmin();

        MailSettingsApplier::applyAtBoot();

        $this->assertSame('smtp.saved.example', Config::get('mail.mailers.smtp.host'));
    }
}
