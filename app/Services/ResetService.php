<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use InvalidArgumentException;

class ResetService
{
    public const CONFIRMATION_PHRASE = 'RESET TRUSTNODE';
    private const RESET_LOCK_KEY = 'trustnode:system-reset-lock';
    private const RESET_LOCK_TTL_SECONDS = 60;

    /**
     * Tables that hold application operational data to be cleared during a reset.
     * Listed in safe dependency order (children before parents).
     */
    protected array $applicationDataTables = [
        'finding_activity_logs',
        'finding_compliance',
        'finding_identities',
        'finding_lifecycle_histories',
        'finding_risk',
        'findings',
        'scan_activity_logs',
        'scan_reports',
        'scans',
        'report_histories',
        'reports',
        'agent_activity_logs',
        'agent_approvals',
        'agent_states',
        'agent_tasks',
        'codebase_projects',
        'local_projects',
        'repositories',
        'asset_activity_logs',
        'asset_tag_asset',
        'asset_tags',
        'assets',
        'asset_groups',
        'target_activity_logs',
        'target_tag_target',
        'target_tags',
        'targets',
        'compliance_assessments',
        'compliance_controls',
        'compliance_frameworks',
        'risk_histories',
        'risk_treatments',
        'risks',
        'import_logs',
        'import_jobs',
        'import_histories',
        'import_files',
        'integration_credentials',
        'integration_histories',
        'integration_jobs',
        'integrations',
        'notifications',
        'personal_access_tokens',
        'user_invitations',
        'team_user',
        'teams',
        'users',
        'organizations',
        'license_installations',
        'settings',
        'failed_jobs',
        'jobs',
        'job_batches',
        'password_reset_tokens',
        'sessions',
    ];

    /**
     * Execute a full application data reset.
     *
     * @param User $adminUser The administrator authorizing the reset
     * @param string $confirmationPhrase Must match CONFIRMATION_PHRASE exactly
     * @param bool $requireBackup Whether to create and verify a backup before clearing data
     * @return array Summary of the reset operation
     */
    public function executeReset(User $adminUser, string $confirmationPhrase, bool $requireBackup = true): array
    {
        if (trim($confirmationPhrase) !== self::CONFIRMATION_PHRASE) {
            throw new InvalidArgumentException('Confirmation phrase does not match. Expected: ' . self::CONFIRMATION_PHRASE);
        }

        $lock = Cache::lock(self::RESET_LOCK_KEY, self::RESET_LOCK_TTL_SECONDS);
        if (!$lock->get()) {
            throw new RuntimeException('A system reset operation is already in progress.');
        }

        $backupDetails = null;

        try {
            if ($requireBackup) {
                $backupDetails = $this->createVerifiedPreResetBackup();
            }

            $clearedCounts = $this->performDatabaseReset();
            $this->invalidateAgentAndSessionCaches();

            Log::warning('TrustNode application reset completed successfully', [
                'admin_id' => $adminUser->id,
                'backup_file' => $backupDetails['file'] ?? 'skipped',
                'cleared_tables' => count($clearedCounts),
            ]);

            return [
                'success' => true,
                'message' => 'TrustNode platform reset successfully completed. All application data has been cleared.',
                'backup' => $backupDetails,
                'cleared_tables' => $clearedCounts,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Clear application-owned tables inside a transaction with foreign key checks temporarily disabled.
     */
    protected function performDatabaseReset(): array
    {
        $cleared = [];

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        try {
            DB::transaction(function () use (&$cleared) {
                foreach ($this->applicationDataTables as $table) {
                    if (Schema::hasTable($table)) {
                        $count = DB::table($table)->count();
                        DB::table($table)->delete();
                        $cleared[$table] = $count;
                    }
                }

                // Ensure seed roles and permissions exist so initial setup can create the first admin
                if (Schema::hasTable('roles') && DB::table('roles')->count() === 0) {
                    $this->seedBaselineRolesAndPermissions();
                }
            });
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            }
        }

        return $cleared;
    }

    /**
     * Clear caches, agent heartbeats, and stored agent credentials.
     */
    protected function invalidateAgentAndSessionCaches(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            Log::error('Failed to flush cache during system reset', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Create and verify a pre-reset database backup.
     */
    protected function createVerifiedPreResetBackup(): array
    {
        $backupDir = storage_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd-His');
        $backupFile = "{$backupDir}/pre_reset_backup_{$timestamp}.sql";

        $dbConnection = config('database.default');
        $dbName = config("database.connections.{$dbConnection}.database");

        if ($dbConnection === 'sqlite') {
            if (file_exists($dbName)) {
                copy($dbName, $backupFile);
            } else {
                file_put_contents($backupFile, "-- SQLite in-memory or empty database\n");
            }
        } else {
            // Self-contained pure-PHP SQL dump generation (requires no external binaries like mysqldump)
            $handle = fopen($backupFile, 'w');
            if ($handle === false) {
                throw new RuntimeException('Unable to open backup destination file for writing.');
            }

            try {
                fwrite($handle, "-- TrustNode Pre-Reset Automated Database Backup\n");
                fwrite($handle, "-- Timestamp: " . date('Y-m-d H:i:s') . "\n");
                fwrite($handle, "-- Database: {$dbName}\n\n");
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

                $tables = DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
                foreach ($tables as $tableObj) {
                    $prop = "Tables_in_{$dbName}";
                    $tableName = $tableObj->$prop ?? current((array) $tableObj);

                    // Table schema
                    $createTable = DB::select("SHOW CREATE TABLE `{$tableName}`");
                    $createSql = $createTable[0]->{'Create Table'} ?? '';
                    fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                    fwrite($handle, "{$createSql};\n\n");

                    // Table rows
                    $rows = DB::table($tableName)->get();
                    if ($rows->count() > 0) {
                        $columnNames = array_keys((array) $rows->first());
                        $escapedColumns = array_map(fn($col) => "`{$col}`", $columnNames);
                        $colList = implode(', ', $escapedColumns);

                        foreach ($rows->chunk(100) as $chunk) {
                            $valuesList = [];
                            foreach ($chunk as $row) {
                                $escapedVals = [];
                                foreach ($columnNames as $col) {
                                    $val = $row->$col;
                                    if ($val === null) {
                                        $escapedVals[] = 'NULL';
                                    } elseif (is_numeric($val) && !is_string($val)) {
                                        $escapedVals[] = $val;
                                    } else {
                                        $escapedVals[] = DB::getPdo()->quote((string) $val);
                                    }
                                }
                                $valuesList[] = '(' . implode(', ', $escapedVals) . ')';
                            }
                            fwrite($handle, "INSERT INTO `{$tableName}` ({$colList}) VALUES\n" . implode(",\n", $valuesList) . ";\n\n");
                        }
                    }
                }

                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            } finally {
                fclose($handle);
            }

            if (!file_exists($backupFile) || filesize($backupFile) === 0) {
                if (file_exists($backupFile)) {
                    @unlink($backupFile);
                }
                throw new RuntimeException('Failed to create verified pre-reset database backup. Reset aborted.');
            }
        }

        $sha256 = hash_file('sha256', $backupFile);

        return [
            'file' => $backupFile,
            'size' => filesize($backupFile),
            'sha256' => $sha256,
        ];
    }

    /**
     * Ensure standard baseline roles exist so setup wizard works after reset.
     */
    protected function seedBaselineRolesAndPermissions(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator', 'description' => 'System administrator with full platform access'],
            ['name' => 'Security Manager', 'slug' => 'security-manager', 'description' => 'Manages security scans, assessments, and policy'],
            ['name' => 'Security Analyst', 'slug' => 'security-analyst', 'description' => 'Analyzes vulnerabilities and manages findings'],
            ['name' => 'Developer', 'slug' => 'developer', 'description' => 'Views and remediates findings for assigned projects'],
            ['name' => 'Auditor', 'slug' => 'auditor', 'description' => 'Read-only compliance and audit access'],
            ['name' => 'Operator', 'slug' => 'operator', 'description' => 'Operational management for scans and agents'],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Read-only view access across the platform'],
        ];

        foreach ($roles as $roleData) {
            DB::table('roles')->insert(array_merge($roleData, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
