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
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider')->default('github');
            $table->string('repository_url', 2048);
            $table->string('repository_id')->nullable();
            $table->string('name');
            $table->string('visibility')->default('public'); // public, private
            $table->string('default_branch')->default('main');
            $table->foreignId('integration_credential_id')->nullable()->constrained('integration_credentials')->nullOnDelete();
            $table->string('status')->default('Connected'); // Connected, Scanning, Failed
            $table->timestamp('last_scan_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
