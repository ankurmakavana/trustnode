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
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->nullable()->constrained('integrations')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, validating, parsing, previewing, importing, completed, failed
            $table->integer('progress')->default(0);
            $table->string('source_type'); // file, scanner, api, url
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->string('filename');
            $table->string('filepath');
            $table->bigInteger('filesize');
            $table->string('mime_type');
            $table->timestamps();
        });

        Schema::create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->string('scanner');
            $table->integer('imported_assets_count')->default(0);
            $table->integer('imported_findings_count')->default(0);
            $table->integer('duplicates_count')->default(0);
            $table->integer('errors_count')->default(0);
            $table->integer('duration')->default(0); // seconds
            $table->string('status')->default('completed'); // completed, failed
            $table->timestamps();
        });

        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_job_id')->constrained('import_jobs')->cascadeOnDelete();
            $table->string('level'); // info, warning, error
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });

        // Add relationships to scans, assets, and findings
        Schema::table('scans', function (Blueprint $table) {
            $table->foreignId('import_job_id')->nullable()->constrained('import_jobs')->nullOnDelete();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('import_job_id')->nullable()->constrained('import_jobs')->nullOnDelete();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('import_job_id')->nullable()->constrained('import_jobs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('findings', function (Blueprint $table) {
            $table->dropForeign(['import_job_id']);
            $table->dropColumn('import_job_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['import_job_id']);
            $table->dropColumn('import_job_id');
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['import_job_id']);
            $table->dropColumn('import_job_id');
        });

        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('import_histories');
        Schema::dropIfExists('import_files');
        Schema::dropIfExists('import_jobs');
    }
};
