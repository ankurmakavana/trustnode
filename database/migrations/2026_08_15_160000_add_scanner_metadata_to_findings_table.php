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
            $table->string('scanner')->nullable()->after('fingerprint');
            $table->string('scanner_plugin_id')->nullable()->after('scanner');
            $table->string('scanner_oid')->nullable()->after('scanner_plugin_id');
            $table->string('protocol')->nullable()->after('scanner_oid');
            $table->string('service')->nullable()->after('protocol');
            $table->timestamp('first_seen')->nullable()->after('service');
            $table->timestamp('last_seen')->nullable()->after('first_seen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropColumn([
                'scanner',
                'scanner_plugin_id',
                'scanner_oid',
                'protocol',
                'service',
                'first_seen',
                'last_seen',
            ]);
        });
    }
};
