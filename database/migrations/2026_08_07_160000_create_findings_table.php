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
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('finding_id')->unique(); // TN-FIND-000001 format
            $table->string('title');
            $table->string('cve')->nullable();
            $table->decimal('cvss_score', 3, 1)->nullable();
            $table->string('severity'); // FindingSeverity
            $table->string('status')->default('open'); // FindingStatus
            $table->string('category'); // Web, Network, Host, Cloud
            $table->string('cwe')->nullable();
            $table->text('description')->nullable();
            $table->text('technical_details')->nullable();
            $table->text('business_impact')->nullable();
            $table->text('remediation')->nullable();
            $table->text('evidence')->nullable();

            // Relationships
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('targets')->nullOnDelete();
            $table->foreignId('scan_id')->nullable()->constrained('scans')->nullOnDelete();
            $table->foreignId('assigned_analyst')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finding_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->string('action');
            $table->json('properties')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_activity_logs');
        Schema::dropIfExists('findings');
    }
};
