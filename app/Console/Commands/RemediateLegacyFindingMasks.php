<?php

namespace App\Console\Commands;

use App\Models\Finding;
use App\Models\FindingActivityLog;
use App\Models\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replaces Secret-category evidence stored by the old partial-masking scanner.
 *
 * Dry run by default. Output contains finding ids and counts only, never evidence values. Each
 * applied change is recorded in the finding's activity log without the value.
 */
class RemediateLegacyFindingMasks extends Command
{
    protected $signature = 'findings:remediate-legacy-masks {--apply : Write the changes (default is a dry run)}';

    protected $description = 'Replace Secret-category evidence stored by the old partial masking with a placeholder';

    public function handle(): int
    {
        // Maintenance command: there is no request user, so only the tenant scope is removed. Matching is
        // limited to one legacy shape, so no other finding is read or changed.
        $matches = Finding::withoutGlobalScope(TenantScope::class)
            ->where('category', 'Secret')
            ->get()
            ->filter(fn (Finding $finding) => $finding->hasLegacyPartialMask())
            ->values();

        $ids = $matches->pluck('id')->all();

        if ($ids === []) {
            $this->info('0 finding(s) matched.');

            return self::SUCCESS;
        }

        $this->line(count($ids) . ' finding(s) matched: ' . implode(', ', $ids));

        if (! $this->option('apply')) {
            $this->info('Dry run: no changes made. Re-run with --apply to write.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($matches) {
            foreach ($matches as $finding) {
                $finding->redactLegacyPartialMask();
                $finding->save();

                FindingActivityLog::create([
                    'finding_id' => $finding->id,
                    'action' => 'legacy_evidence_redacted',
                    'properties' => ['reason' => 'Evidence from the old partial masking replaced with a placeholder.'],
                ]);
            }
        });

        $this->info('Applied to ' . count($ids) . ' finding(s).');

        return self::SUCCESS;
    }
}
