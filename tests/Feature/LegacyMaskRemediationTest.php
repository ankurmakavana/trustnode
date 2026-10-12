<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\FindingActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Remediation for Secret-category evidence stored by the old partial-masking scanner.
 * Values below are synthetic. The "legacy" value is built from the old masking shape only.
 */
class LegacyMaskRemediationTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_MASK = 'ABCD****WXYZ';
    private const PLACEHOLDER = '[REDACTED: legacy partial mask]';

    private function legacySecretFinding(string $evidence = self::LEGACY_MASK): Finding
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $adminRole = \App\Models\Role::where('slug', \App\Enums\UserRole::ADMINISTRATOR->value)->firstOrFail();
        $admin = \App\Models\User::factory()->create(['role_id' => $adminRole->id]);

        $finding = Finding::factory()->create([
            'category' => 'Secret',
            'evidence' => 'placeholder',
            'created_by' => $admin->id,
        ]);

        // Write the legacy value directly, as the old code did, bypassing the model hook.
        DB::table('findings')->where('id', $finding->id)->update(['evidence' => $evidence]);

        return $finding;
    }

    public function test_old_partial_mask_on_a_secret_finding_is_detected_and_replaced_in_memory()
    {
        $finding = new Finding(['category' => 'Secret', 'evidence' => self::LEGACY_MASK]);

        $this->assertTrue($finding->redactLegacyPartialMask());
        $this->assertSame(self::PLACEHOLDER, $finding->evidence);
    }

    public function test_all_asterisk_legacy_mask_is_detected()
    {
        $finding = new Finding(['category' => 'Secret', 'evidence' => '********']);

        $this->assertTrue($finding->redactLegacyPartialMask());
    }

    public function test_non_secret_category_is_never_changed()
    {
        $finding = new Finding(['category' => 'Injection', 'evidence' => self::LEGACY_MASK]);

        $this->assertFalse($finding->redactLegacyPartialMask());
        $this->assertSame(self::LEGACY_MASK, $finding->evidence);
    }

    public function test_placeholder_or_ordinary_secret_text_is_not_changed()
    {
        $finding = new Finding(['category' => 'Secret', 'evidence' => '[REDACTED: SEC-SECRET-AWS]']);

        $this->assertFalse($finding->redactLegacyPartialMask());
    }

    public function test_dry_run_reports_ids_only_and_changes_nothing()
    {
        $finding = $this->legacySecretFinding();

        $this->artisan('findings:remediate-legacy-masks')
            ->expectsOutputToContain("finding(s) matched: {$finding->id}")
            ->doesntExpectOutputToContain(self::LEGACY_MASK)
            ->assertSuccessful();

        $this->assertSame(self::LEGACY_MASK, DB::table('findings')->where('id', $finding->id)->value('evidence'));
        $this->assertDatabaseCount('finding_activity_logs', 0);
    }

    public function test_apply_replaces_the_value_and_records_an_activity_entry_without_it()
    {
        $finding = $this->legacySecretFinding();

        $this->artisan('findings:remediate-legacy-masks', ['--apply' => true])
            ->doesntExpectOutputToContain(self::LEGACY_MASK)
            ->assertSuccessful();

        $this->assertSame(self::PLACEHOLDER, DB::table('findings')->where('id', $finding->id)->value('evidence'));

        $log = FindingActivityLog::where('finding_id', $finding->id)->where('action', 'legacy_evidence_redacted')->firstOrFail();
        $this->assertStringNotContainsString('ABCD', (string) json_encode($log->properties));
        $this->assertStringNotContainsString('WXYZ', (string) json_encode($log->properties));
    }

    public function test_apply_is_idempotent()
    {
        $finding = $this->legacySecretFinding();

        $this->artisan('findings:remediate-legacy-masks', ['--apply' => true])->assertSuccessful();
        $this->artisan('findings:remediate-legacy-masks', ['--apply' => true])->assertSuccessful();

        $this->assertSame(self::PLACEHOLDER, DB::table('findings')->where('id', $finding->id)->value('evidence'));
        $this->assertDatabaseCount('finding_activity_logs', 1);
    }
}
