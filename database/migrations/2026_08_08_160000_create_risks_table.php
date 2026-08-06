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
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('risk_id')->unique(); // TN-RISK-000001 format
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('business_impact')->nullable();
            $table->text('technical_impact')->nullable();
            $table->string('likelihood'); // Rare, Unlikely, Possible, Likely, Almost Certain
            $table->string('impact'); // Negligible, Minor, Moderate, Major, Catastrophic
            $table->integer('risk_score')->default(0);
            $table->string('risk_level'); // Critical, High, Medium, Low
            $table->string('status')->default('Open'); // Open, Mitigating, Accepted, Resolved, Closed
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->date('review_date')->nullable();
            $table->boolean('accepted')->default(false);
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('risk_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('risks')->cascadeOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('risk_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('risks')->cascadeOnDelete();
            $table->string('treatment_type'); // Mitigate, Transfer, Avoid, Accept
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status')->default('Open');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('finding_risk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained('risks')->cascadeOnDelete();
            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_risk');
        Schema::dropIfExists('risk_treatments');
        Schema::dropIfExists('risk_histories');
        Schema::dropIfExists('risks');
    }
};
