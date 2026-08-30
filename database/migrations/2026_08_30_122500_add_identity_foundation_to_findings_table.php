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
        Schema::table('scans', function (Blueprint $table) {
            $table->foreignId('local_project_id')->nullable()->constrained('local_projects')->nullOnDelete();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('finding_identity_id')->nullable()->constrained('finding_identities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['finding_identity_id']);
            $table->dropColumn('finding_identity_id');
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['local_project_id']);
            $table->dropColumn('local_project_id');
        });
    }
};
