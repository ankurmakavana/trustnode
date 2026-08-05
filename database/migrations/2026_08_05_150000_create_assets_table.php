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
        Schema::create('asset_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name')->index();
            $table->string('type', 50)->index(); // App\Enums\Asset\AssetType
            $table->string('value', 2048); // the IP, domain, URL, etc.
            $table->text('description')->nullable();
            $table->string('criticality', 50)->default('medium')->index(); // App\Enums\Asset\AssetCriticality
            $table->string('status', 50)->default('active')->index(); // App\Enums\Asset\AssetStatus
            $table->decimal('risk_score', 4, 2)->default(0.00)->index(); // Risk Score between 0.00 and 10.00
            $table->string('owner')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('asset_group_id')->nullable()->constrained('asset_groups')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes()->index();
        });

        Schema::create('asset_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#cbd5e1'); // hex color code
            $table->timestamps();
        });

        Schema::create('asset_tag_asset', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('asset_tag_id')->constrained('asset_tags')->cascadeOnDelete();
            $table->unique(['asset_id', 'asset_tag_id']);
        });

        Schema::create('asset_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
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
        Schema::dropIfExists('asset_activity_logs');
        Schema::dropIfExists('asset_tag_asset');
        Schema::dropIfExists('asset_tags');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_groups');
    }
};
