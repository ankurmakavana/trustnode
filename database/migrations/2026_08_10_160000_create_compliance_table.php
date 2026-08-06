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
        Schema::create('compliance_frameworks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique(); // OWASP, MITRE, ISO, PCI, NIST, SOC2, CWE, CVE, CIS
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_controls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('framework_id')->constrained('compliance_frameworks')->cascadeOnDelete();
            $table->string('code'); // e.g. A01, T1190, A.8.20
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('finding_compliance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('compliance_controls')->cascadeOnDelete();
            $table->string('status')->default('Failed'); // Passed, Failed, Not Assessed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('compliance_assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->string('status')->default('Active'); // Active, Closed
            $table->decimal('score', 5, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compliance_assessments');
        Schema::dropIfExists('finding_compliance');
        Schema::dropIfExists('compliance_controls');
        Schema::dropIfExists('compliance_frameworks');
    }
};
