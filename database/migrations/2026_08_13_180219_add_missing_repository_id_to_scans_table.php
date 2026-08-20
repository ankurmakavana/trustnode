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
        if (!Schema::hasColumn('scans', 'repository_id')) {
            Schema::table('scans', function (Blueprint $table) {
                $table->foreignId('repository_id')->nullable()->constrained('repositories')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('scans', 'repository_id')) {
            Schema::table('scans', function (Blueprint $table) {
                $table->dropForeign(['repository_id']);
                $table->dropColumn('repository_id');
            });
        }
    }
};
