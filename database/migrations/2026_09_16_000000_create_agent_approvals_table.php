<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->index();
            $table->string('operation');
            $table->string('capability');
            $table->json('scope')->nullable();
            $table->json('constraints')->nullable();
            $table->string('request_fingerprint')->index();
            $table->string('status')->default('pending'); // pending, approved, rejected, revoked, expired
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_approvals');
    }
};
