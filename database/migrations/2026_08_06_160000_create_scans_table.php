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
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // ScanType enum value
            $table->string('engine'); // ScanEngine enum value
            $table->string('target'); // Target value or name reference string
            $table->string('schedule')->nullable(); // cron schedule string, e.g. "0 0 * * *" or null for manual
            $table->string('status')->default('queued'); // ScanStatus
            $table->integer('progress')->default(0); // 0-100 progress value
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration')->nullable(); // duration in seconds
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('scan_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained('scans')->cascadeOnDelete();
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
        Schema::dropIfExists('scan_activity_logs');
        Schema::dropIfExists('scans');
    }
};
