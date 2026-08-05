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
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name')->index();
            $table->string('type', 50)->index(); // App\Enums\Target\TargetType
            $table->string('value', 2048); // domain name, URL, etc.
            $table->string('environment', 50)->default('production')->index(); // App\Enums\Target\TargetEnvironment
            $table->string('criticality', 50)->default('medium')->index(); // App\Enums\Target\TargetCriticality
            $table->string('status', 50)->default('active')->index(); // App\Enums\Target\TargetStatus
            $table->text('description')->nullable();
            $table->text('scope_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::create('target_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#cbd5e1'); // hex color code
            $table->timestamps();
        });

        Schema::create('target_tag_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('targets')->cascadeOnDelete();
            $table->foreignId('target_tag_id')->constrained('target_tags')->cascadeOnDelete();
            $table->unique(['target_id', 'target_tag_id']);
        });

        Schema::create('target_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained('targets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index(); // e.g. created, updated, status_changed, etc.
            $table->json('properties')->nullable(); // Old vs New values
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_activity_logs');
        Schema::dropIfExists('target_tag_target');
        Schema::dropIfExists('target_tags');
        Schema::dropIfExists('targets');
    }
};
