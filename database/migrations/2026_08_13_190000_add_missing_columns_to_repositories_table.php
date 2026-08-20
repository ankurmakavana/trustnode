<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing columns to repositories table.
 *
 * Root cause: The create_repositories_table migration (batch 3) ran against
 * an already-existing stub table that only had id + timestamps. This migration
 * adds all the columns that should have been created originally, preserving
 * any existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (! Schema::hasColumn('repositories', 'uuid')) {
                $table->uuid('uuid')->unique()->after('id');
            }
            if (! Schema::hasColumn('repositories', 'provider')) {
                $table->string('provider')->default('github')->after('uuid');
            }
            if (! Schema::hasColumn('repositories', 'repository_url')) {
                $table->string('repository_url', 2048)->after('provider');
            }
            if (! Schema::hasColumn('repositories', 'repository_id')) {
                $table->string('repository_id')->nullable()->after('repository_url');
            }
            if (! Schema::hasColumn('repositories', 'name')) {
                $table->string('name')->after('repository_id');
            }
            if (! Schema::hasColumn('repositories', 'visibility')) {
                $table->string('visibility')->default('public')->after('name');
            }
            if (! Schema::hasColumn('repositories', 'default_branch')) {
                $table->string('default_branch')->default('main')->after('visibility');
            }
            if (! Schema::hasColumn('repositories', 'integration_credential_id')) {
                $table->foreignId('integration_credential_id')
                    ->nullable()
                    ->constrained('integration_credentials')
                    ->nullOnDelete()
                    ->after('default_branch');
            }
            if (! Schema::hasColumn('repositories', 'status')) {
                $table->string('status')->default('Connected')->after('integration_credential_id');
            }
            if (! Schema::hasColumn('repositories', 'last_scan_at')) {
                $table->timestamp('last_scan_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('repositories', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('last_scan_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('repositories', function (Blueprint $table) {
            $table->dropForeign(['integration_credential_id']);
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'uuid', 'provider', 'repository_url', 'repository_id',
                'name', 'visibility', 'default_branch',
                'integration_credential_id', 'status', 'last_scan_at', 'created_by',
            ]);
        });
    }
};
