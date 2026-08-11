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
        Schema::table('findings', function (Blueprint $table) {
            $table->integer('port')->nullable()->after('cvss_score');
            $table->string('url', 2048)->nullable()->after('port');
            $table->string('fingerprint')->nullable()->after('url')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn(['port', 'url', 'fingerprint']);
        });
    }
};
