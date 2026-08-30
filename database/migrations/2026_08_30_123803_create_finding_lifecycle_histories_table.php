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
        Schema::create('finding_lifecycle_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_identity_id')->constrained('finding_identities')->cascadeOnDelete();
            $table->foreignId('scan_id')->constrained('scans')->cascadeOnDelete();
            $table->string('state'); // new, recurring, resolved, regression
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['finding_identity_id', 'scan_id'], 'flh_identity_scan_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finding_lifecycle_histories');
    }
};
