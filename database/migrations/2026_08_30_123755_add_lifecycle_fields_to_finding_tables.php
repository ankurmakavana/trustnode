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
        Schema::table('finding_identities', function (Blueprint $table) {
            $table->string('lifecycle_status')->default('new')->after('fingerprint');
            $table->timestamp('resolved_at')->nullable()->after('last_seen_at');
            $table->foreignId('resolved_by_scan_id')->nullable()->after('resolved_at')->constrained('scans')->nullOnDelete();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->string('lifecycle_status')->default('new')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn('lifecycle_status');
        });

        Schema::table('finding_identities', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_scan_id']);
            $table->dropColumn(['lifecycle_status', 'resolved_at', 'resolved_by_scan_id']);
        });
    }
};
