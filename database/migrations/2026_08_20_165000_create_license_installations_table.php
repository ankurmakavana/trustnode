<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->string('installation_id')->unique();
            $table->text('installation_secret')->nullable();
            $table->text('installation_token')->nullable();
            $table->string('license_status')->nullable();
            $table->json('entitlements')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('last_successful_validation_at')->nullable();
            $table->timestamp('grace_expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_installations');
    }
};