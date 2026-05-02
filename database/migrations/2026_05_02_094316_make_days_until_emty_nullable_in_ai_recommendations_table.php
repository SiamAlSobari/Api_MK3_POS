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
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->integer('days_until_emty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_recommendations', function (Blueprint $table) {
            $table->integer('days_until_emty')->default(0)->nullable(false)->change();
        });
    }
};
