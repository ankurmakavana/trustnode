<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add organization_id to assets (created_by-owned)
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('asset_group_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to targets (created_by-owned)
        Schema::table('targets', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('value')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to repositories (created_by-owned)
        Schema::table('repositories', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('integration_credential_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to scans (created_by-owned or derived from repository)
        Schema::table('scans', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('repository_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to local_projects (created_by-owned)
        Schema::table('local_projects', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('path')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to integrations (system-owned, per organization)
        Schema::table('integrations', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });

        // Add organization_id to settings (system-owned, per organization)
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('assets_organization_id_index');
            $table->dropForeignKey('assets_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->dropIndex('targets_organization_id_index');
            $table->dropForeignKey('targets_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('repositories', function (Blueprint $table) {
            $table->dropIndex('repositories_organization_id_index');
            $table->dropForeignKey('repositories_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->dropIndex('scans_organization_id_index');
            $table->dropForeignKey('scans_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('local_projects', function (Blueprint $table) {
            $table->dropIndex('local_projects_organization_id_index');
            $table->dropForeignKey('local_projects_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropIndex('integrations_organization_id_index');
            $table->dropForeignKey('integrations_organization_id_foreign');
            $table->dropColumn('organization_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex('settings_organization_id_index');
            $table->dropForeignKey('settings_organization_id_foreign');
            $table->dropColumn('organization_id');
        });
    }
};
