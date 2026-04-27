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
        Schema::create('ai_recommendation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_recommendation_id')->constrained('ai_recommendations')->cascadeOnDelete();
            $table->string('action_type');
            $table->json('action_data')->nullable();
            $table->boolean('is_executed')->default(false);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_recommendation_actions');
    }
};

