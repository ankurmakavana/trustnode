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
        Schema::table('codebase_projects', function (Blueprint $table) {
            $table->string('source_type', 50)->default('workspace_mount')->after('path');
            $table->string('agent_id', 128)->nullable()->after('source_type')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('codebase_projects', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'agent_id']);
        });
    }
};
