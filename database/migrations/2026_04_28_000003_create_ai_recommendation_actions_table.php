<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_recommendation_id')->constrained()->onDelete('cascade');
            $table->enum('action_type', ['DONE', 'IGNORE'])->nullable();
            $table->timestamp('action_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendation_actions');
    }
};