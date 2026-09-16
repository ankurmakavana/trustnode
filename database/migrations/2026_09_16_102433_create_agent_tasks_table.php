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
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->index();
            $table->string('type');
            $table->json('payload');
            $table->string('status')->index(); // pending, processing, failed, completed
            $table->integer('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // For FIFO dequeuing based on creation + id
            $table->index(['agent_id', 'status', 'created_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
