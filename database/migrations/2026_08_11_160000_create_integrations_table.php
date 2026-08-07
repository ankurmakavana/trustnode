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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code'); // nmap, greenbone, nessus, burp, github, gitlab, jira, slack, teams
            $table->string('type'); // scanner, collaboration, vcs, ticketing
            $table->string('status')->default('Disconnected'); // Connected, Disconnected
            $table->string('host')->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->boolean('tls')->default(false);
            $table->string('health_status')->default('Unreachable'); // Healthy, Unreachable, Authentication Failed, Timeout
            $table->timestamp('last_check_at')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('integration_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('status')->default('Queued'); // Queued, Running, Completed, Failed, Cancelled
            $table->integer('duration')->default(0); // in seconds
            $table->integer('imported_records')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('action'); // Connected, Validated, Disconnected, Import
            $table->text('description')->nullable();
            $table->string('status')->default('Success'); // Success, Failed
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_histories');
        Schema::dropIfExists('integration_jobs');
        Schema::dropIfExists('integration_credentials');
        Schema::dropIfExists('integrations');
    }
};
