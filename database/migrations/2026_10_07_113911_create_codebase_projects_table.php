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
        Schema::create('codebase_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('name')->index();
            $table->string('path', 512); // Relative path inside workspace mount
            $table->string('scan_profile', 50)->default('standard'); // App\Enums\CodebaseProject\ScanProfile
            $table->boolean('continuous_scan_enabled')->default(false);
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 50)->default('active')->index(); // App\Enums\CodebaseProject\CodebaseProjectStatus
            $table->timestamps();
            $table->softDeletes()->index();

            $table->unique(['organization_id', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codebase_projects');
    }
};
