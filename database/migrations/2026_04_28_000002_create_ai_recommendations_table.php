<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('current_stock')->default(0);
            $table->integer('recommed_restok_qty')->default(0);
            $table->string('risk_level')->nullable(); // LOW, MEDIUM, HIGH, CRITICAL
            $table->integer('days_until_emty')->default(0);
            $table->date('estimated_emty_date')->nullable();
            $table->string('risk')->nullable();
            $table->text('description')->nullable();
            $table->integer('risk_point')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};