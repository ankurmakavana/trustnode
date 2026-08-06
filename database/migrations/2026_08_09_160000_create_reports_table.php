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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('report_id')->unique(); // TR-REP-2026-0001 format
            $table->string('title');
            $table->string('type'); // Executive Summary, Technical Assessment, Risk Report, Compliance Report, Asset Coverage, Scan Coverage
            $table->string('status')->default('Generated'); // Generated, Archived
            $table->json('options')->nullable(); // Config or mapping options
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('report_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('action'); // Generated, Viewed, Updated, Archived
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_histories');
        Schema::dropIfExists('reports');
    }
};
