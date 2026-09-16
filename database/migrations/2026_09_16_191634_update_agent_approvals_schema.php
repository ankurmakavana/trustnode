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
        Schema::table('agent_approvals', function (Blueprint $table) {
            if (Schema::hasColumn('agent_approvals', 'scope')) {
                $table->dropColumn('scope');
            }
            if (Schema::hasColumn('agent_approvals', 'constraints')) {
                $table->dropColumn('constraints');
            }
            // First drop existing index on request_fingerprint to avoid issues when adding unique, 
            // but actually we can just add a composite unique constraint.
            // But if there are duplicates already, it will fail. We don't have to worry about existing duplicates for now since it's dev.
            $table->unique(['agent_id', 'request_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('agent_approvals', function (Blueprint $table) {
            $table->dropUnique(['agent_id', 'request_fingerprint']);
            $table->json('scope')->nullable();
            $table->json('constraints')->nullable();
        });
    }
};
