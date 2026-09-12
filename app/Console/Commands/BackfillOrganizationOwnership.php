<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Integration;
use App\Models\LocalProject;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\Scan;
use App\Models\Setting;
use App\Models\Target;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackfillOrganizationOwnership extends Command
{
    protected $signature = 'trustnode:backfill-organization-ownership {--force}';
    protected $description = 'Safely backfill organization_id for core resources based on ownership determinism';

    public function handle(): int
    {
        $this->info('🔍 Auditing organization ownership for core resources...');

        $blockers = [];

        // Audit each resource
        $blockers = array_merge($blockers, $this->auditAssets());
        $blockers = array_merge($blockers, $this->auditTargets());
        $blockers = array_merge($blockers, $this->auditRepositories());
        $blockers = array_merge($blockers, $this->auditScans());
        $blockers = array_merge($blockers, $this->auditLocalProjects());
        $blockers = array_merge($blockers, $this->auditIntegrations());
        $blockers = array_merge($blockers, $this->auditSettings());

        // Report blockers
        if (!empty($blockers)) {
            $this->error('❌ BACKFILL BLOCKED — Ambiguous or unresolvable ownership detected:');
            foreach ($blockers as $blocker) {
                $this->error("   • $blocker");
            }
            $this->error("\n⚠️  Cannot safely backfill with unresolved records. Fix ownership manually or update the audit logic.");
            return self::FAILURE;
        }

        $this->info('✅ All records have deterministic ownership. Safe to backfill.');

        if (!$this->option('force')) {
            if (!$this->confirm('Proceed with backfill?')) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        // Backfill
        $this->info('⏳ Backfilling organization ownership...');

        $this->backfillAssets();
        $this->backfillTargets();
        $this->backfillRepositories();
        $this->backfillScans();
        $this->backfillLocalProjects();
        $this->backfillIntegrations();
        $this->backfillSettings();

        $this->info('✅ Backfill complete.');

        // Validate no NULLs remain
        $nullCounts = [
            'assets' => Asset::whereNull('organization_id')->count(),
            'targets' => Target::whereNull('organization_id')->count(),
            'repositories' => Repository::whereNull('organization_id')->count(),
            'scans' => Scan::whereNull('organization_id')->count(),
            'local_projects' => LocalProject::whereNull('organization_id')->count(),
            'integrations' => Integration::whereNull('organization_id')->count(),
            'settings' => Setting::whereNull('organization_id')->count(),
        ];

        $hasNulls = array_filter($nullCounts);
        if ($hasNulls) {
            $this->error('❌ Validation failed — NULL organization_id records remain:');
            foreach ($hasNulls as $table => $count) {
                $this->error("   • $table: $count records");
            }
            return self::FAILURE;
        }

        $this->info('✅ Validation passed — no NULL organization_id records.');
        return self::SUCCESS;
    }

    private function auditAssets(): array
    {
        $blockers = [];
        $unresolved = Asset::whereNull('organization_id')->get();

        foreach ($unresolved as $asset) {
            $orgs = $asset->creator?->organizations ?? collect();
            if ($orgs->count() === 0) {
                $blockers[] = "Asset {$asset->id} creator has no organization";
            } elseif ($orgs->count() > 1) {
                $blockers[] = "Asset {$asset->id} creator belongs to multiple organizations";
            }
        }

        return $blockers;
    }

    private function auditTargets(): array
    {
        $blockers = [];
        $unresolved = Target::whereNull('organization_id')->get();

        foreach ($unresolved as $target) {
            $orgs = $target->creator?->organizations ?? collect();
            if ($orgs->count() === 0) {
                $blockers[] = "Target {$target->id} creator has no organization";
            } elseif ($orgs->count() > 1) {
                $blockers[] = "Target {$target->id} creator belongs to multiple organizations";
            }
        }

        return $blockers;
    }

    private function auditRepositories(): array
    {
        $blockers = [];
        $unresolved = Repository::whereNull('organization_id')->get();

        foreach ($unresolved as $repo) {
            $orgs = $repo->creator?->organizations ?? collect();
            if ($orgs->count() === 0) {
                $blockers[] = "Repository {$repo->id} creator has no organization";
            } elseif ($orgs->count() > 1) {
                $blockers[] = "Repository {$repo->id} creator belongs to multiple organizations";
            }
        }

        return $blockers;
    }

    private function auditScans(): array
    {
        $blockers = [];
        $unresolved = Scan::whereNull('organization_id')->get();

        foreach ($unresolved as $scan) {
            // Try to get from repository first
            if ($scan->repository_id) {
                $repo = $scan->repository;
                if ($repo && $repo->organization_id) {
                    continue; // Resolvable via repository
                }
            }

            // Fall back to creator's organization
            $orgs = $scan->creator?->organizations ?? collect();
            if ($orgs->count() === 0) {
                $blockers[] = "Scan {$scan->id} has no repository and creator has no organization";
            } elseif ($orgs->count() > 1) {
                $blockers[] = "Scan {$scan->id} has no repository and creator belongs to multiple organizations";
            }
        }

        return $blockers;
    }

    private function auditLocalProjects(): array
    {
        $blockers = [];
        $unresolved = LocalProject::whereNull('organization_id')->get();

        foreach ($unresolved as $project) {
            $orgs = $project->creator?->organizations ?? collect();
            if ($orgs->count() === 0) {
                $blockers[] = "LocalProject {$project->id} creator has no organization";
            } elseif ($orgs->count() > 1) {
                $blockers[] = "LocalProject {$project->id} creator belongs to multiple organizations";
            }
        }

        return $blockers;
    }

    private function auditIntegrations(): array
    {
        $blockers = [];
        $unresolved = Integration::whereNull('organization_id')->count();

        if ($unresolved > 0) {
            $blockers[] = "Integrations ({$unresolved} records) have no ownership source; cannot backfill safely";
        }

        return $blockers;
    }

    private function auditSettings(): array
    {
        $blockers = [];
        $unresolved = Setting::whereNull('organization_id')->count();

        if ($unresolved > 0) {
            $blockers[] = "Settings ({$unresolved} records) have no ownership source; cannot backfill safely";
        }

        return $blockers;
    }

    private function backfillAssets(): void
    {
        $updated = 0;
        Asset::whereNull('organization_id')->each(function (Asset $asset) use (&$updated) {
            $orgs = $asset->creator?->organizations;
            if ($orgs && $orgs->count() === 1) {
                $asset->organization_id = $orgs->first()->id;
                $asset->save();
                $updated++;
            }
        });
        $this->info("   ✓ Assets: {$updated} records backfilled");
    }

    private function backfillTargets(): void
    {
        $updated = 0;
        Target::whereNull('organization_id')->each(function (Target $target) use (&$updated) {
            $orgs = $target->creator?->organizations;
            if ($orgs && $orgs->count() === 1) {
                $target->organization_id = $orgs->first()->id;
                $target->save();
                $updated++;
            }
        });
        $this->info("   ✓ Targets: {$updated} records backfilled");
    }

    private function backfillRepositories(): void
    {
        $updated = 0;
        Repository::whereNull('organization_id')->each(function (Repository $repo) use (&$updated) {
            $orgs = $repo->creator?->organizations;
            if ($orgs && $orgs->count() === 1) {
                $repo->organization_id = $orgs->first()->id;
                $repo->save();
                $updated++;
            }
        });
        $this->info("   ✓ Repositories: {$updated} records backfilled");
    }

    private function backfillScans(): void
    {
        $updated = 0;
        Scan::whereNull('organization_id')->each(function (Scan $scan) use (&$updated) {
            // Prefer repository ownership
            if ($scan->repository_id && $scan->repository?->organization_id) {
                $scan->organization_id = $scan->repository->organization_id;
                $scan->save();
                $updated++;
                return;
            }

            // Fall back to creator's organization
            $orgs = $scan->creator?->organizations;
            if ($orgs && $orgs->count() === 1) {
                $scan->organization_id = $orgs->first()->id;
                $scan->save();
                $updated++;
            }
        });
        $this->info("   ✓ Scans: {$updated} records backfilled");
    }

    private function backfillLocalProjects(): void
    {
        $updated = 0;
        LocalProject::whereNull('organization_id')->each(function (LocalProject $project) use (&$updated) {
            $orgs = $project->creator?->organizations;
            if ($orgs && $orgs->count() === 1) {
                $project->organization_id = $orgs->first()->id;
                $project->save();
                $updated++;
            }
        });
        $this->info("   ✓ LocalProjects: {$updated} records backfilled");
    }

    private function backfillIntegrations(): void
    {
        $this->warn('   ⚠️  Integrations: skipped (no safe ownership source)');
    }

    private function backfillSettings(): void
    {
        $this->warn('   ⚠️  Settings: skipped (no safe ownership source)');
    }
}
